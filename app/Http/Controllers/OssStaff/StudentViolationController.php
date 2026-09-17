<?php

namespace App\Http\Controllers\OssStaff;

use App\Http\Controllers\Controller;
use App\Http\Controllers\OssStaff\Concerns\ResolvesOssStaffActor;
use App\Models\EvidenceSecurityEvent;
use App\Models\Program;
use App\Models\User;
use App\Models\Violation;
use App\Models\ViolationCategory;
use App\Models\ViolationEvidence;
use App\Models\ViolationType;
use App\Models\Year;
use App\Services\EvidenceSecurityService;
use App\Services\ViolationRecordingService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * OSS Staff violation recording.
 *
 * Operationally the same workflow the Security module provides (student record
 * lookup, the 4-step record-violation wizard, the submitted-violations tracker,
 * the report + PDF export, evidence viewing and the capture security events),
 * but the ACTOR is an OSS Staff ADMIN account:
 *
 *   recorder_type = 'admin'
 *   recorder_id   = the OSS Staff admin id
 *   recorder_name = the OSS Staff member's full name
 *   uploader_type = 'admin'
 *   uploader_id   = the OSS Staff admin id
 *   audit actor   = 'admin'
 *
 * `recorded_by_security_id` is deliberately NEVER written here — that column
 * addresses the Security account that recorded a row, and an OSS Staff member
 * is not a Security account.
 *
 * The shared, identity-free logic (evidence storage, report building, PDF
 * rendering, offense-level/type lookups) lives in
 * {@see ViolationRecordingService} so the two modules cannot drift apart while
 * keeping separate audit identities.
 */
class StudentViolationController extends Controller
{
    use ResolvesOssStaffActor;

    public function __construct(private readonly ViolationRecordingService $recording)
    {
    }

    /**
     * Resolve the configured evidence storage disk (S3-ready on Railway).
     *
     * Delegates to the central resolver so this controller cannot diverge from
     * the disk used for uploads and from the one used to serve files back.
     */
    private function evidenceDisk(): \Illuminate\Filesystem\FilesystemAdapter
    {
        return $this->recording->evidenceDisk();
    }

    /**
     * Display the Violations page.
     *
     * The page carries two tabs: "Student Record" (the listing below) and
     * "Submitted Violations" (the OSS Staff member's own submission tracker).
     * This single action supplies both datasets and `?tab=submitted` selects
     * the tracker tab.
     */
    public function index(Request $request)
    {
        return view('oss-staff.violations.students', $this->buildViolationsPagePayload(
            $request,
            $request->input('tab') === 'submitted' ? 'submitted' : 'students'
        ));
    }

    /**
     * Legacy entry point for the standalone Submitted Violations URL.
     *
     * Keeps existing notification deep links and bookmarks working by
     * rendering the combined Violations page with the tracker tab selected.
     */
    public function submitted(Request $request)
    {
        return view('oss-staff.violations.students', $this->buildViolationsPagePayload($request, 'submitted'));
    }

    /**
     * Build every dataset the combined Violations page needs: the Student
     * Record listing plus the Submitted Violations tracker.
     */
    private function buildViolationsPagePayload(Request $request, string $activeTab): array
    {
        $search = trim((string) $request->input('search', ''));
        $programId = $request->input('program_id');
        $yearId = $request->input('year_id');

        $students = User::whereHas('violations', function ($query) {
                $this->applyRecorderScope($query);
            })
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $inner) use ($search) {
                    $inner->where('student_number', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%");
                });
            })
            ->when(!empty($programId), function (Builder $query) use ($programId) {
                $query->whereHas('studentInfo', function (Builder $infoQuery) use ($programId) {
                    $infoQuery->where('program_id', $programId);
                });
            })
            ->when(!empty($yearId), function (Builder $query) use ($yearId) {
                $query->whereHas('studentInfo', function (Builder $infoQuery) use ($yearId) {
                    $infoQuery->where('year_id', $yearId);
                });
            })
            ->with(['violations' => function ($query) {
                $this->applyRecorderScope($query);
                $query->latest('violation_date')->take(5);
            }])
            ->with([
                'studentInfo.program',
                'studentInfo.year',
            ])
            ->withCount([
                'violations as violations_count' => function ($query) {
                    $this->applyRecorderScope($query);
                },
            ])
            ->orderByDesc('violations_count')
            ->paginate(15)
            ->withQueryString();

        $categories = ViolationCategory::orderBy('category_name')->get();
        $programs = Program::orderBy('program_name')->get(['program_id', 'program_name']);
        $years = Year::orderBy('year')->get(['year_id', 'year']);

        // ---- Submitted Violations tab: the OSS Staff member's own submissions
        $status = (string) $request->input('status', '');
        $status = in_array($status, ['pending', 'confirmed', 'denied', 'rejected'], true) ? $status : '';

        $violations = Violation::with([
                'student.studentInfo.program',
                'student.studentInfo.year',
                'student.studentInfo.section',
                'violationType.violationCategory',
                'evidence',
            ])
            ->where(function ($query) {
                // Submitted Violations is the tracker for Pending, Confirmed AND
                // Rejected submissions, so denied rows are shown.
                $this->applyRecorderScope($query, true);
            })
            ->when($status !== '', function ($query) use ($status) {
                $statusValue = $status === 'rejected' ? 'denied' : $status;
                $query->where('status', $statusValue);
            })
            ->when($request->filled('submitted_search'), function ($query) use ($request) {
                // The tracker has its own search parameter: it shares a page with
                // the Student Record listing, whose `search` param must not leak
                // into these results (or the other way round).
                $search = '%' . trim((string) $request->input('submitted_search')) . '%';
                $query->where(function ($q) use ($search) {
                    $q->where('student_number', 'like', $search)
                        ->orWhereHas('student', fn ($s) => $s->where('first_name', 'like', $search)->orWhere('last_name', 'like', $search))
                        ->orWhereHas('violationType', fn ($v) => $v->where('violation_type', 'like', $search));
                });
            })
            ->latest('violation_date')
            ->paginate(15, ['*'], 'submitted_page')
            ->withQueryString();

        $counts = [
            'pending' => Violation::where('status', Violation::STATUS_PENDING)
                ->where(fn ($q) => $this->applyRecorderScope($q, true))->count(),
            'confirmed' => Violation::where('status', Violation::STATUS_CONFIRMED)
                ->where(fn ($q) => $this->applyRecorderScope($q, true))->count(),
            'denied' => Violation::where('status', Violation::STATUS_DENIED)
                ->where(fn ($q) => $this->applyRecorderScope($q, true))->count(),
        ];

        // When arriving from a notification deep link, highlight the referenced
        // violation row (only if it is on the currently loaded page).
        $highlightViolationId = (int) $request->integer('highlight_violation');
        if ($highlightViolationId > 0
            && ! $violations->getCollection()->contains('violation_id', $highlightViolationId)) {
            $highlightViolationId = 0;
        }

        return array_merge(
            compact(
                'students',
                'categories',
                'programs',
                'years',
                'search',
                'programId',
                'yearId',
                'violations',
                'status',
                'counts',
                'highlightViolationId'
            ),
            ['activeViolationsTab' => $activeTab]
        );
    }

    /**
     * Display violations for a specific student.
     *
     * Only violations that actually reflect on the student's official record
     * are shown here with their CURRENT administrator-reviewed status:
     *   - confirmed -> "Confirmed"
     *   - denied    -> "Rejected"
     * Pending/unreviewed recordings are intentionally NOT shown on the student
     * record until the administrator has reviewed them; the OSS Staff member
     * tracks those in "Submitted Violations".
     */
    public function show(string $student_number)
    {
        $student = User::where('student_number', $student_number)
            ->with(['violations' => function ($query) {
                // Denied rows are included so an administrator REJECTION is
                // reflected on the record rather than silently disappearing.
                $this->applyRecorderScope($query, true);

                // Only reviewed rows reflect on the record: Confirmed (approved)
                // and Denied (rejected). Pending rows are still awaiting review.
                $query->whereIn('status', [
                    Violation::STATUS_CONFIRMED,
                    Violation::STATUS_DENIED,
                ]);

                $query->latest('violation_date');
            }, 'violations.violationType.violationCategory'])
            ->firstOrFail();

        $violations = $student->violations;

        // A violation may be highlighted when arriving from a notification deep
        // link.
        $highlightViolationId = (int) request()->integer('highlight_violation');
        if ($highlightViolationId > 0 && $violations->doesntContain('violation_id', $highlightViolationId)) {
            $highlightViolationId = 0;
        }

        $violationStats = $this->getViolationStats($student_number);

        return view('oss-staff.violations.show', compact('student', 'violations', 'violationStats', 'highlightViolationId'));
    }

    /**
     * Search for students.
     *
     * Identical to the Security module's search: the student directory and the
     * violation count describe the STUDENT, not the recorder, so they are not
     * restricted to this account's own recordings.
     */
    public function search(Request $request)
    {
        $query = (string) $request->input('search', $request->input('q', ''));

        if (strlen($query) < 2) {
            return response()->json([]);
        }

        $students = User::where('student_number', 'like', "%{$query}%")
            ->orWhere('first_name', 'like', "%{$query}%")
            ->orWhere('last_name', 'like', "%{$query}%")
            ->with([
                'violations',
                'studentInfo.program',
                'studentInfo.year',
                'studentInfo.section',
                'studentInfo.studentStatus',
            ])
            ->withCount('violations')
            ->limit(10)
            ->get();

        return response()->json($students->map(function ($student) {
            return [
                'id' => $student->student_number,
                'student_number' => $student->student_number,
                'first_name' => $student->first_name,
                'middle_name' => $student->middle_name,
                'last_name' => $student->last_name,
                'text' => "{$student->student_number} - {$student->last_name}, {$student->first_name}",
                'violations_count' => $student->violations_count,
                // PUPTracker ACCOUNT status — the client warns before recording
                // a violation for an inactive student. The server independently
                // re-checks the rule in StoreViolationRequest.
                'account_active' => $student->isActiveAccount(),
                'program' => [
                    'program_name' => optional($student->studentInfo?->program)->program_name,
                ],
                'year' => [
                    'year_name' => optional($student->studentInfo?->year)->year,
                ],
                'section' => [
                    'section_name' => optional($student->studentInfo?->section)->section_name,
                ],
                'student_status' => [
                    'status_name' => optional($student->studentInfo?->studentStatus)->status_name,
                ],
            ];
        }));
    }

    public function getViolationTypes(Request $request): JsonResponse
    {
        return response()->json(
            $this->recording->violationTypesForCategory($request->integer('category_id') ?: null)
        );
    }

    public function previewViolation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_number' => ['required', 'string'],
            'violation_type_id' => ['required', 'integer'],
        ]);

        $preview = $this->recording->previewViolationData(
            $validated['student_number'],
            (int) $validated['violation_type_id']
        );

        if ($preview === null) {
            return response()->json([
                'message' => 'Violation type not found.',
            ], 404);
        }

        return response()->json($preview);
    }

    public function store(\App\Http\Requests\StoreViolationRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $violationType = ViolationType::findOrFail($validated['violation_type_id']);

        DB::beginTransaction();

        try {
            $exists = Violation::where('student_number', $validated['student_number'])
                ->where(function ($query) use ($violationType) {
                    $query->where('violation_type', (string) $violationType->violation_type_id)
                        ->orWhere('violation_type', $violationType->violation_type);
                })
                ->whereDate('violation_date', Carbon::parse($validated['violation_date'])->toDateString())
                // A denied violation should not block re-recording the same real
                // incident; pending + confirmed still block a same-day duplicate.
                ->notDenied()
                ->exists();

            if ($exists) {
                DB::rollBack();

                return response()->json([
                    'message' => 'A violation of this type has already been recorded for this student today.',
                ], 422);
            }

            $ossStaffId = $this->ossStaffId();
            $ossStaffLabel = $this->ossStaffName();

            $violation = Violation::create([
                'student_number' => $validated['student_number'],
                'violation_type' => $violationType->violation_type_id,
                'violation_date' => $validated['violation_date'],
                'description' => trim($validated['description']),
                // `recorder_id` references admins.id and the OSS Staff account
                // IS an admins row, so the identity is persisted directly.
                'recorder_id' => $ossStaffId,
                'recorder_type' => 'admin',
                'recorder_name' => $ossStaffLabel,
                // OSS-Staff-recorded violations are sent to the admin for
                // confirmation before they count toward the student's record.
                'status' => Violation::STATUS_PENDING,
            ]);

            // `recorded_by_security_id` is intentionally NOT set: it addresses
            // the SECURITY account that recorded a row, and OSS Staff is an
            // admin account. Writing it here would falsely identify the OSS
            // Staff member as Security.

            // Store supporting evidence privately (never public) and keep the
            // DB rows in the same transaction so a storage failure rolls back.
            $captureSessionId = $request->input('capture_session_id')
                ? (string) $request->input('capture_session_id')
                : null;
            $this->recording->storeEvidence($violation, $request, 'admin', $ossStaffId, $captureSessionId);

            DB::commit();

            // Notify the admin so they can confirm or deny the recorded
            // violation (target-aware). The student is notified after the
            // admin decision, not on the initial record.
            try {
                $student = $violation->student;
                $studentName = $student
                    ? trim(($student->first_name ?? '') . ' ' . ($student->last_name ?? ''))
                    : $validated['student_number'];

                app(\App\Services\AdminNotificationService::class)->create(
                    type: 'violation_confirmation',
                    title: 'Violation Awaiting Confirmation',
                    message: 'OSS Staff recorded a violation for ' . $studentName . ' (' . $validated['student_number'] . '): ' . $violationType->violation_type . '. Confirm or deny this violation.',
                    targetType: 'violation',
                    targetId: (int) $violation->violation_id,
                );
            } catch (\Throwable $e) {
                // Notification must never break the violation recording.
            }

            return response()->json([
                'success' => true,
                'message' => 'Violation recorded successfully. It has been sent to the administrator for confirmation.',
                'data' => $violation,
            ]);
        } catch (\Throwable $exception) {
            DB::rollBack();

            // Log technical detail server-side only; never leak to the client.
            Log::error('OSS Staff violation store failed', [
                'student_number' => $request->input('student_number'),
                'error' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]);

            return \App\Support\Responses::unexpectedError();
        }
    }

    /**
     * Resubmit a previously REJECTED (denied) violation with corrections.
     *
     * Only the recording OSS Staff member may resubmit, and only while the
     * original row is still denied. A resubmission creates a NEW pending
     * violation for the same student + violation type with the corrected
     * details and any new evidence. The original denied row is preserved for
     * audit. Same-day resubmission after rejection is allowed (the denied row
     * does not count as an effective violation).
     */
    public function resubmitViolation(Request $request, int $violationId)
    {
        if (! $this->ossStaffUser()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $original = Violation::query()->find($violationId);

        if (! $original) {
            return back()->withErrors(['violation' => 'Violation not found.']);
        }

        if (strtolower((string) $original->status) !== Violation::STATUS_DENIED) {
            return back()->withErrors(['violation' => 'Only a rejected violation can be resubmitted.']);
        }

        if (! $this->recorderMatches($original)) {
            abort(403);
        }

        $validated = $request->validate([
            'violation_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:1000'],
            'evidence' => ['nullable', 'array', 'max:5'],
            'evidence.*' => ['file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $violationType = $original->resolvedViolationType() ?? ViolationType::find($original->violation_type);

        DB::beginTransaction();

        try {
            $ossStaffId = $this->ossStaffId();
            $ossStaffLabel = $this->ossStaffName();

            $violation = Violation::create([
                'student_number' => $original->student_number,
                'violation_type' => $violationType?->violation_type_id ?? $original->violation_type,
                'violation_date' => $validated['violation_date'],
                'description' => trim($validated['description']),
                'recorder_id' => $ossStaffId,
                'recorder_type' => 'admin',
                'recorder_name' => $ossStaffLabel,
                'status' => Violation::STATUS_PENDING,
            ]);

            // `recorded_by_security_id` is deliberately NOT set (see store()).

            $captureSessionId = $request->input('capture_session_id')
                ? (string) $request->input('capture_session_id')
                : null;
            $this->recording->storeEvidence($violation, $request, 'admin', $ossStaffId, $captureSessionId);

            DB::commit();

            // Notify admins a (corrected) violation is awaiting confirmation.
            try {
                $studentName = trim(($original->student?->first_name ?? '') . ' ' . ($original->student?->last_name ?? ''));
                $studentName = $studentName !== '' ? $studentName : ($original->student_number ?? 'a student');
                $typeLabel = $violationType?->violation_type ?? 'the recorded violation';

                app(\App\Services\AdminNotificationService::class)->create(
                    type: 'violation_confirmation',
                    title: 'Violation Resubmitted for Confirmation',
                    message: 'OSS Staff resubmitted a corrected violation for ' . $studentName . ' (' . $original->student_number . '): ' . $typeLabel . '. Confirm or deny this violation.',
                    targetType: 'violation',
                    targetId: (int) $violation->violation_id,
                );
            } catch (\Throwable $e) {
                // Notification must never break the resubmission.
            }

            return back()->with('success', 'Violation resubmitted successfully. It has been sent to the administrator for confirmation.');
        } catch (\Throwable $exception) {
            DB::rollBack();

            Log::error('OSS Staff violation resubmit failed', [
                'violation_id' => $violationId,
                'student_number' => $original?->student_number,
                'error' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]);

            return back()->withErrors([
                'violation' => 'Unable to resubmit the violation. Please try again later.',
            ]);
        }
    }

    public function report(Request $request)
    {
        $report = $this->buildReportPayload($request);

        // A PDF download is requested explicitly (?format=pdf). Without it the
        // existing printable HTML report is returned, unchanged.
        if (strtolower((string) $request->input('format', '')) === 'pdf') {
            return $this->recording->reportPdf(
                $report,
                'oss-staff.violations.report',
                'oss-staff.violations.students',
                'OSS Staff'
            );
        }

        return view('oss-staff.violations.report', $report);
    }

    /**
     * Assemble the report data. The report content, ordering and filters come
     * from the shared {@see ViolationRecordingService}; only the "reported by"
     * identity is module-specific.
     *
     * @return array<string, mixed>
     */
    private function buildReportPayload(Request $request): array
    {
        $period = (string) $request->input('period', 'last7');
        $period = in_array($period, ['today', 'last7', 'last30', 'all', 'custom'], true) ? $period : 'last7';

        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        // The report range selector is the ONLY filter for this report. The
        // student-list filters (search / program / year) belong to the Student
        // Record tab and are deliberately NOT applied here.
        $reportStudents = $this->recording->buildReportStudents(
            $period,
            $dateFrom,
            $dateTo,
            function ($query) {
                // Authorization: only this OSS Staff member's own recorded
                // violations.
                $this->applyRecorderScope($query);
            }
        );

        $filtersApplied = match ($period) {
            'today' => 'Today (PHT)',
            'last7' => 'Last 7 Days',
            'last30' => 'Last 30 Days',
            'all' => 'All My Recorded Violations',
            'custom' => $this->recording->formatCustomDateFilter($dateFrom, $dateTo),
            default => 'Last 7 Days',
        };

        return [
            'reportStudents' => $reportStudents,
            'generatedAt' => $this->recording->phtNow(),
            'filtersApplied' => $filtersApplied,
            'reportedBy' => $this->ossStaffName(),
        ];
    }

    /**
     * Get violation statistics for a student.
     */
    private function getViolationStats(string $student_number)
    {
        $violations = Violation::where('student_number', $student_number)
            ->where(function ($query) {
                $this->applyRecorderScope($query);
            })
            // Only confirmed violations reflect on the official student record.
            ->where('status', Violation::STATUS_CONFIRMED)
            ->with(['violationType.violationCategory']);

        $byCategory = (clone $violations)
            ->get()
            ->groupBy(fn ($violation) => $violation->violation_category_display)
            ->map(function ($items, $categoryName) {
                return (object) [
                    'category_name' => $categoryName,
                    'total' => $items->count(),
                ];
            })
            ->values();

        $severityCounts = (clone $violations)
            ->leftJoin(
                'violation_type_tbl',
                'violation_tbl.violation_type',
                '=',
                'violation_type_tbl.violation_type_id'
            )
            ->selectRaw('COALESCE(violation_type_tbl.severity_level, 0) as severity_level, COUNT(*) as count')
            ->groupBy('severity_level')
            ->pluck('count', 'severity_level');

        return [
            'total' => $violations->count(),
            'major' => (int) ($severityCounts[1] ?? 0),
            'minor' => (int) ($severityCounts[2] ?? 0),
            'by_category' => $byCategory,
        ];
    }

    /**
     * Accept a security/audit event reported by the browser monitoring layer
     * during the controlled evidence capture workflow.
     *
     * These are DETECTION / DETERRENCE indicators (page blur, visibility
     * change, possible screen capture, PrintScreen key, camera permission
     * denied, camera error, security notice accepted, capture abandoned). The
     * browser never reports a "confirmed screenshot" because browsers cannot
     * prove that — only indicators that require administrative review.
     *
     * The event's ACTOR is recorded as 'admin' with the OSS Staff id, so the
     * audit trail never misattributes OSS Staff activity to Security.
     */
    public function logSecurityEvent(Request $request): JsonResponse
    {
        $ossStaff = $this->ossStaffUser();
        if (! $ossStaff) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $allowed = [
            EvidenceSecurityEvent::CAMERA_PORTAL_OPENED,
            EvidenceSecurityEvent::SECURITY_NOTICE_ACCEPTED,
            EvidenceSecurityEvent::CAMERA_INITIALIZED,
            EvidenceSecurityEvent::CAMERA_PERMISSION_DENIED,
            EvidenceSecurityEvent::CAMERA_ERROR,
            EvidenceSecurityEvent::EVIDENCE_CAPTURED,
            EvidenceSecurityEvent::PAGE_BLURRED,
            EvidenceSecurityEvent::PAGE_FOCUS_RESTORED,
            EvidenceSecurityEvent::PAGE_VISIBILITY_CHANGED,
            EvidenceSecurityEvent::POSSIBLE_SCREEN_CAPTURE,
            EvidenceSecurityEvent::PRINTSCREEN_DETECTED,
            EvidenceSecurityEvent::CAPTURE_ABANDONED,
        ];

        $eventType = (string) $request->input('event_type', '');
        if (! in_array($eventType, $allowed, true)) {
            return response()->json(['message' => 'Unsupported event.'], 422);
        }

        $violationId = $request->input('violation_id') ? (int) $request->input('violation_id') : null;
        $evidenceId = $request->input('evidence_id') ? (int) $request->input('evidence_id') : null;
        $captureSessionId = $request->input('capture_session_id') ? (string) $request->input('capture_session_id') : null;
        $severity = (string) $request->input('severity', 'info');
        $severity = in_array($severity, ['info', 'warning', 'critical'], true) ? $severity : 'info';
        $meta = $request->input('meta');
        $meta = is_array($meta) ? $meta : (is_string($meta) && $meta !== '' ? ['detail' => $meta] : null);

        app(EvidenceSecurityService::class)->logEvent(
            $eventType,
            $violationId,
            $evidenceId,
            $severity,
            $request,
            $meta,
            'admin',
            (int) $ossStaff->id,
            $captureSessionId,
        );

        return response()->json(['success' => true]);
    }

    /**
     * Serve a violation evidence file from private storage.
     *
     * OSS Staff members may view evidence attached to violations they recorded.
     * The file is streamed through the authorized responder, so no permanent
     * public/object-storage URL is ever exposed.
     *
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function downloadEvidence(ViolationEvidence $evidence)
    {
        $violation = $evidence->violation;

        if (! $violation) {
            abort(404);
        }

        // Only the recording OSS Staff member may open it.
        $ossStaff = $this->ossStaffUser();
        if (! $ossStaff || ! $this->recorderMatches($violation)) {
            abort(403);
        }

        if (! $evidence->disk_path) {
            return \App\Services\PrivateFileResponder::stream(
                'evidence',
                null,
                $evidence->original_name,
                ['evidence_id' => $evidence->evidence_id ?? null]
            );
        }

        // Audit the access + record an evidence security event. The actor is
        // 'admin' (the OSS Staff account's guard), never 'security'.
        app(EvidenceSecurityService::class)->auditEvidenceAccess(
            'EVIDENCE_VIEWED',
            $evidence,
            'admin',
            (int) $ossStaff->id,
            request(),
        );

        return \App\Services\PrivateFileResponder::stream(
            'evidence',
            $evidence->disk_path,
            $evidence->original_name,
            ['evidence_id' => $evidence->evidence_id ?? null]
        );
    }
}
