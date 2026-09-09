<?php

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use App\Models\DisciplinarySanction;
use App\Models\EvidenceSecurityEvent;
use App\Models\Program;
use App\Models\ViolationCategory;
use App\Models\ViolationType;
use App\Models\Year;
use App\Services\EvidenceSecurityService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use App\Models\Violation;
use App\Models\ViolationEvidence;
use Illuminate\Support\Collection;

class StudentViolationController extends Controller
{
    private const REPORT_TIMEZONE = 'Asia/Manila';

    /**
     * Resolve the configured evidence storage disk (S3-ready on Railway).
     */
    private function evidenceDisk(): \Illuminate\Filesystem\FilesystemAdapter
    {
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk((string) config('filesystems.evidence_disk', 'local'));

        return $disk;
    }

    /**
     * Display a listing of students with violations
     */
    public function index()
    {
        $search = trim((string) request('search', ''));
        $programId = request('program_id');
        $yearId = request('year_id');

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

        return view('security.violations.students', compact('students', 'categories', 'programs', 'years', 'search', 'programId', 'yearId'));
    }

    /**
     * Display the submitting officer's own violation reports with their
     * administrative review status (Pending / Confirmed / Rejected).
     */
    public function submitted(Request $request)
    {
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
                // Submitted Violations is the officer's tracker for Pending,
                // Confirmed AND Rejected submissions, so denied rows are shown.
                $this->applyRecorderScope($query, true);
            })
            ->when($status !== '', function ($query) use ($status) {
                $statusValue = $status === 'rejected' ? 'denied' : $status;
                $query->where('status', $statusValue);
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = '%' . trim($request->input('search')) . '%';
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

        return view('security.violations.submitted', compact('violations', 'status', 'counts', 'highlightViolationId'));
    }

    /**
     * Display violations for a specific student.
     *
     * Only violations that actually reflect on the student's official record
     * are shown here (confirmed). Pending/unconfirmed recordings are tracked in
     * "Submitted Violations" and are intentionally NOT shown on the student
     * record page until the administrator confirms them. Denied/rejected rows
     * are handled through the resubmit flow in Submitted Violations.
     */
    public function show(string $student_number)
    {
        $student = User::where('student_number', $student_number)
            ->with(['violations' => function ($query) {
                $this->applyRecorderScope($query);
                // Only confirmed rows reflect on the student's record.
                $query->where('status', Violation::STATUS_CONFIRMED);
                $query->latest('violation_date');
            }, 'violations.violationType.violationCategory'])
            ->firstOrFail();

        $violations = $student->violations;

        // A confirmed violation may be highlighted when arriving from a
        // notification deep link.
        $highlightViolationId = (int) request()->integer('highlight_violation');
        if ($highlightViolationId > 0 && $violations->doesntContain('violation_id', $highlightViolationId)) {
            $highlightViolationId = 0;
        }

        $violationStats = $this->getViolationStats($student_number);

        return view('security.violations.show', compact('student', 'violations', 'violationStats', 'highlightViolationId'));
    }

    /**
     * Search for students
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
        $categoryId = $request->get('category_id');

        if (!$categoryId) {
            return response()->json([]);
        }

        $types = ViolationType::where('violation_category_id', $categoryId)
            ->orderBy('violation_type')
            ->get([
                'violation_type_id',
                'violation_type',
                'violation_description',
                'resolution_number',
            ]);

        return response()->json($types);
    }

    public function previewViolation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_number' => ['required', 'string'],
            'violation_type_id' => ['required', 'integer'],
        ]);

        $violationType = ViolationType::with('violationCategory')
            ->find($validated['violation_type_id']);

        if (!$violationType) {
            return response()->json([
                'message' => 'Violation type not found.',
            ], 404);
        }

        $previousCount = Violation::where('student_number', $validated['student_number'])
            ->where(function ($query) use ($violationType) {
                $query->where('violation_type', (string) $violationType->violation_type_id)
                    ->orWhere('violation_type', $violationType->violation_type);
            })
            ->effective()
            ->count();

        $currentOffenseNumber = $previousCount + 1;
        $offenseLevel = $this->formatOffenseLevel($currentOffenseNumber);

        $sanction = DisciplinarySanction::where('violation_type_id', $violationType->violation_type_id)
            ->where('offense_level', $offenseLevel)
            ->first();

        return response()->json([
            'category' => optional($violationType->violationCategory)->category_name,
            'violation_type' => $violationType->violation_type,
            'severity_level' => $violationType->severity_level,
            'offense_level' => $offenseLevel,
            'sanction' => $sanction?->disciplinary_sanction ?? 'No disciplinary sanction configured.',
        ]);
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

            $security = Auth::guard('security')->user();
            $securityLabel = $this->buildSecurityAccountName($security);
            $securityId = $security?->id;

            $violationData = [
                'student_number' => $validated['student_number'],
                'violation_type' => $violationType->violation_type_id,
                'violation_date' => $validated['violation_date'],
                'description' => trim($validated['description']),
                // recorder_id references admins.id; security accounts are in a different table.
                'recorder_id' => null,
                'recorder_type' => 'security',
                'recorder_name' => $securityLabel,
                // Security-recorded violations are sent to the admin for
                // confirmation before they count toward the student's record.
                'status' => Violation::STATUS_PENDING,
            ];

            // Persist the exact recording security account so notifications about
            // this violation can be addressed to the right officer.
            if ($securityId !== null && \Illuminate\Support\Facades\Schema::hasColumn('violation_tbl', 'recorded_by_security_id')) {
                $violationData['recorded_by_security_id'] = $securityId;
            }

            $violation = Violation::create($violationData);

            // Store supporting evidence privately (never public) and keep the
            // DB rows in the same transaction so a storage failure rolls back.
            $captureSessionId = $request->input('capture_session_id')
                ? (string) $request->input('capture_session_id')
                : null;
            $this->storeEvidence($violation, $request, $security, $securityId, $captureSessionId);

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
                    message: 'Security recorded a violation for ' . $studentName . ' (' . $validated['student_number'] . '): ' . $violationType->violation_type . '. Confirm or deny this violation.',
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
            Log::error('Security violation store failed', [
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
     * Only the recording security officer may resubmit, and only while the
     * original row is still denied. A resubmission creates a NEW pending
     * violation for the same student + violation type with the corrected
     * details and any new evidence. The original denied row is preserved for
     * audit. Same-day resubmission after rejection is allowed (the denied row
     * does not count as an effective violation).
     */
    public function resubmitViolation(Request $request, int $violationId)
    {
        $security = Auth::guard('security')->user();
        if (! $security) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $original = Violation::query()->find($violationId);

        if (! $original) {
            return back()->withErrors(['violation' => 'Violation not found.']);
        }

        if (strtolower((string) $original->status) !== Violation::STATUS_DENIED) {
            return back()->withErrors(['violation' => 'Only a rejected violation can be resubmitted.']);
        }

        if (! $this->recorderMatches($original, $security)) {
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
            $securityLabel = $this->buildSecurityAccountName($security);
            $securityId = $security->id;

            $violationData = [
                'student_number' => $original->student_number,
                'violation_type' => $violationType?->violation_type_id ?? $original->violation_type,
                'violation_date' => $validated['violation_date'],
                'description' => trim($validated['description']),
                'recorder_id' => null,
                'recorder_type' => 'security',
                'recorder_name' => $securityLabel,
                'status' => Violation::STATUS_PENDING,
            ];

            if (\Illuminate\Support\Facades\Schema::hasColumn('violation_tbl', 'recorded_by_security_id')) {
                $violationData['recorded_by_security_id'] = $securityId;
            }

            $violation = Violation::create($violationData);

            $captureSessionId = $request->input('capture_session_id')
                ? (string) $request->input('capture_session_id')
                : null;
            $this->storeEvidence($violation, $request, $security, $securityId, $captureSessionId);

            DB::commit();

            // Notify admins a (corrected) violation is awaiting confirmation.
            try {
                $studentName = trim(($original->student?->first_name ?? '') . ' ' . ($original->student?->last_name ?? ''));
                $studentName = $studentName !== '' ? $studentName : ($original->student_number ?? 'a student');
                $typeLabel = $violationType?->violation_type ?? 'the recorded violation';

                app(\App\Services\AdminNotificationService::class)->create(
                    type: 'violation_confirmation',
                    title: 'Violation Resubmitted for Confirmation',
                    message: 'Security resubmitted a corrected violation for ' . $studentName . ' (' . $original->student_number . '): ' . $typeLabel . '. Confirm or deny this violation.',
                    targetType: 'violation',
                    targetId: (int) $violation->violation_id,
                );
            } catch (\Throwable $e) {
                // Notification must never break the resubmission.
            }

            return back()->with('success', 'Violation resubmitted successfully. It has been sent to the administrator for confirmation.');
        } catch (\Throwable $exception) {
            DB::rollBack();

            Log::error('Security violation resubmit failed', [
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

    /**
     * Store uploaded evidence for a violation privately and record audit/security
     * events. Runs inside the caller's DB transaction.
     */
    private function storeEvidence(Violation $violation, Request $request, $security, ?int $securityId, ?string $captureSessionId = null): void
    {
        $evidenceFiles = $request->allFiles()['evidence'] ?? [];

        // Capture metadata is submitted as parallel arrays (one entry per
        // staged evidence item). The camera path marks capture_method as
        // 'camera' and records when the photo was taken. This is audit
        // metadata only — it never proves identity.
        $captureMethods = (array) $request->input('capture_methods', []);
        $capturedAts = (array) $request->input('captured_ats', []);
        $evidenceService = app(EvidenceSecurityService::class);

        foreach ($evidenceFiles as $index => $evidenceFile) {
            $evidenceCode = $evidenceService->nextEvidenceCode();
            $directory = 'violation-evidence/' . $violation->violation_id;
            $extension = strtolower($evidenceFile->getClientOriginalExtension());
            // Server-side filename derived from the unique evidence code —
            // the original filename is never trusted or used on disk.
            $filename = $evidenceCode . '.' . ($extension !== '' ? $extension : 'bin');
            $disk = $this->evidenceDisk();

            $isCamera = strcasecmp((string) ($captureMethods[$index] ?? ''), 'camera') === 0;
            $capturedAt = isset($capturedAts[$index]) && $capturedAts[$index] !== ''
                ? Carbon::parse($capturedAts[$index])
                : null;

            // Build the row first so we can derive the official path + hash.
            $evidence = ViolationEvidence::create([
                'evidence_code' => $evidenceCode,
                'capture_session_id' => $captureSessionId,
                'violation_id' => $violation->violation_id,
                'disk_path' => $directory . '/' . $filename,
                'original_name' => $evidenceFile->getClientOriginalName(),
                'mime_type' => $evidenceFile->getMimeType(),
                'capture_method' => $isCamera
                    ? ViolationEvidence::CAPTURE_CAMERA
                    : ViolationEvidence::CAPTURE_UPLOAD,
                // The client `watermarked` flag is never proof. The server
                // re-bakes the watermark and records server_watermarked.
                'watermarked' => false,
                'server_watermarked' => false,
                'captured_at' => $capturedAt,
                'file_size' => $evidenceFile->getSize(),
                'ip_address' => $evidenceService->clientIp($request),
                'user_agent' => $evidenceService->userAgent($request),
                'uploader_type' => 'security',
                'uploader_id' => $securityId,
            ]);

            // Final trusted image: server-side watermark (images only), stored
            // on the private disk, with SHA-256 of the FINAL bytes.
            $store = app(\App\Services\EvidenceWatermarkService::class)->storeOfficialFile(
                $evidenceFile,
                $directory,
                $filename,
                $evidenceCode,
                (string) ($violation->student_number ?? ''),
                $capturedAt?->format('Y-m-d H:i:s'),
            );

            $storedPath = $store['path'];
            if ($storedPath === '' || ! $disk->exists($storedPath)) {
                throw new \RuntimeException('Unable to store evidence file.');
            }

            $evidence->update([
                'disk_path' => $storedPath,
                'file_size' => $disk->size($storedPath),
                'server_watermarked' => $store['server_watermarked'],
                'watermarked' => $store['server_watermarked'] || $isCamera,
                'sha256_hash' => $store['sha256'],
            ]);

            $evidenceService->logEvent(
                EvidenceSecurityEvent::EVIDENCE_UPLOADED,
                $violation->violation_id,
                $evidence->evidence_id,
                'info',
                $request,
                [
                    'method' => $evidence->capture_method,
                    'code' => $evidenceCode,
                    'sha256' => $store['sha256'],
                    'server_watermarked' => $store['server_watermarked'],
                ],
                'security',
                $securityId,
                $captureSessionId,
            );
        }
    }

    public function report(Request $request)
    {
        $period = (string) $request->input('period', 'last7');
        $period = in_array($period, ['today', 'last7', 'last30', 'all', 'custom'], true) ? $period : 'last7';

        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $reportStudents = $this->buildReportStudents($period, $dateFrom, $dateTo);

        $security = Auth::guard('security')->user();
        $reportedBy = $this->buildSecurityAccountName($security);

        $filtersApplied = match ($period) {
            'today' => 'Today (PHT)',
            'last7' => 'Last 7 Days',
            'last30' => 'Last 30 Days',
            'all' => 'All My Recorded Violations',
            'custom' => $this->formatCustomDateFilter($dateFrom, $dateTo),
            default => 'Last 7 Days',
        };

        return view('security.violations.report', [
            'reportStudents' => $reportStudents,
            'generatedAt' => $this->phtNow(),
            'filtersApplied' => $filtersApplied,
            'reportedBy' => $reportedBy,
        ]);
    }

    /**
     * Get violation statistics for a student
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

    private function buildReportStudents(string $period = 'last7', ?string $dateFrom = null, ?string $dateTo = null): Collection
    {
        $violationsQuery = Violation::with([
                'student.studentInfo.program',
                'student.studentInfo.year',
                'student.studentInfo.section',
                'violationType.violationCategory',
                'violationType.disciplinarySanctions',
            ])
            ->where(function ($query) {
                $this->applyRecorderScope($query);
            });

        $phtNow = $this->phtNow();

        if ($period === 'today') {
            $violationsQuery->whereBetween('violation_date', [
                $phtNow->copy()->startOfDay(),
                $phtNow->copy()->endOfDay(),
            ]);
        } elseif ($period === 'last7') {
            $violationsQuery->where('violation_date', '>=', $phtNow->copy()->subDays(7));
        } elseif ($period === 'last30') {
            $violationsQuery->where('violation_date', '>=', $phtNow->copy()->subDays(30));
        } elseif ($period === 'custom') {
            $from = $this->normalizeDateBoundary($dateFrom, false);
            $to = $this->normalizeDateBoundary($dateTo, true);

            if ($from !== null && $to !== null) {
                $violationsQuery->whereBetween('violation_date', [$from, $to]);
            } elseif ($from !== null) {
                $violationsQuery->where('violation_date', '>=', $from);
            } elseif ($to !== null) {
                $violationsQuery->where('violation_date', '<=', $to);
            }
        }

        $violations = $violationsQuery
            ->latest('violation_date')
            ->get();

        return $violations
            ->groupBy('student_number')
            ->map(function ($studentViolations) {
                $firstViolation = $studentViolations->first();
                $student = $firstViolation?->student;

                $detailedRecords = $studentViolations
                    ->sortByDesc('violation_date')
                    ->values()
                    ->map(function (Violation $record) {
                        $offenseLevel = $record->offense_level ?: 'N/A';
                        $resolvedViolationType = $record->resolvedViolationType();

                        $sanctionText = 'N/A';
                        if (!empty($resolvedViolationType?->violation_type_id)) {
                            $disciplinarySanction = DisciplinarySanction::where(
                                    'violation_type_id',
                                    $resolvedViolationType->violation_type_id
                                )
                                ->whereRaw('LOWER(TRIM(offense_level)) = ?', [strtolower(trim($offenseLevel))])
                                ->first();

                            if (!$disciplinarySanction) {
                                $disciplinarySanction = DisciplinarySanction::where(
                                        'violation_type_id',
                                        $resolvedViolationType->violation_type_id
                                    )
                                    ->first();
                            }

                            $sanctionText = $disciplinarySanction?->disciplinary_sanction ?? 'N/A';
                        }

                        $status = 'N/A';
                        if ($sanctionText !== 'N/A') {
                            $status = str_contains(strtolower($sanctionText), 'warning') ? 'Warning' : 'Sanction';
                        }

                        return (object) [
                            'category' => $record->violation_category_display,
                            'type' => $record->violation_type_display,
                            'offense_level' => $offenseLevel,
                            'remarks' => $record->description ?: 'No remarks',
                            'date_recorded' => $record->violation_date,
                            'status' => $status,
                            'sanction' => $sanctionText,
                            'recorded_by' => $record->recorded_by_display,
                        ];
                    });

                return (object) [
                    'student' => $student,
                    'records' => $detailedRecords,
                ];
            })
            ->values()
            ->sortBy(function ($entry) {
                return trim(($entry->student?->last_name ?? '') . ', ' . ($entry->student?->first_name ?? ''));
            })
            ->values();
    }

    private function normalizeDateBoundary(?string $date, bool $endOfDay): ?Carbon
    {
        if (empty($date)) {
            return null;
        }

        try {
            $parsed = Carbon::parse($date, self::REPORT_TIMEZONE);
            return $endOfDay ? $parsed->endOfDay() : $parsed->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function phtNow(): Carbon
    {
        return Carbon::now(self::REPORT_TIMEZONE);
    }

    private function formatCustomDateFilter(?string $dateFrom, ?string $dateTo): string
    {
        $from = $this->normalizeDateBoundary($dateFrom, false);
        $to = $this->normalizeDateBoundary($dateTo, true);

        if ($from && $to) {
            return 'Custom Date Range: ' . $from->format('M d, Y') . ' to ' . $to->format('M d, Y');
        }

        if ($from) {
            return 'Custom Date Range: From ' . $from->format('M d, Y');
        }

        if ($to) {
            return 'Custom Date Range: Until ' . $to->format('M d, Y');
        }

        return 'Custom Date Range: No valid dates provided';
    }

    private function formatOffenseLevel(int $count): string
    {
        if ($count === 1) {
            return '1st Offense';
        }

        if ($count === 2) {
            return '2nd Offense';
        }

        if ($count === 3) {
            return '3rd Offense';
        }

        $suffix = match (true) {
            $count % 100 >= 11 && $count % 100 <= 13 => 'th',
            $count % 10 === 1 => 'st',
            $count % 10 === 2 => 'nd',
            $count % 10 === 3 => 'rd',
            default => 'th',
        };

        return "{$count}{$suffix} Offense";
    }

    private function applyRecorderScope($query, bool $includeDenied = false): void
    {
        $security = Auth::guard('security')->user();
        $securityLabel = $this->buildSecurityAccountName($security);
        $legacySecurityEmailLabel = !empty($security?->email) ? 'Security: ' . $security->email : null;
        $legacySecurityIdLabel = 'Security #' . ($security?->id ?? 'Unknown');

        $query->where('recorder_type', 'security')
            ->where(function ($innerQuery) use ($securityLabel, $legacySecurityEmailLabel, $legacySecurityIdLabel) {
                $innerQuery->where('recorder_name', $securityLabel)
                    ->orWhere('recorder_name', $legacySecurityIdLabel);

                if ($legacySecurityEmailLabel !== null) {
                    $innerQuery->orWhere('recorder_name', $legacySecurityEmailLabel);
                }
            });

        // Denied violations are not real violations, so by default they are
        // excluded from the officer's record/dashboard views. The "Submitted
        // Violations" tracker opts in ($includeDenied = true) because officers
        // must see their rejected submissions there in order to resubmit them.
        if (! $includeDenied) {
            $query->notDenied();
        }
    }

    private function buildSecurityRecorderLabel($securityId, ?string $email): string
    {
        if (!empty($email)) {
            return 'Security: ' . $email;
        }

        return 'Security #' . ($securityId ?? 'Unknown');
    }

    private function buildSecurityAccountName($security): string
    {
        if (!$security) {
            return 'Security Account';
        }

        $info = $security->securityInfo;
        $legacyInfo = $security->securityProfile;

        $fullName = trim(implode(' ', array_filter([
            $info?->firstname ?? $legacyInfo?->firstname,
            $info?->middlename ?? $legacyInfo?->middlename,
            $info?->lastname ?? $legacyInfo?->lastname,
        ], fn ($value) => !empty($value))));

        if ($fullName !== '') {
            return $fullName;
        }

        return $security->email ?: 'Security Account';
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
     */
    public function logSecurityEvent(Request $request): JsonResponse
    {
        $security = Auth::guard('security')->user();
        if (! $security) {
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
            'security',
            (int) $security->id,
            $captureSessionId,
        );

        return response()->json(['success' => true]);
    }

    /**
     * Serve a violation evidence file from private storage.
     *
     * Security officers may view evidence attached to violations they recorded.
     *
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function downloadEvidence(ViolationEvidence $evidence)
    {
        $violation = $evidence->violation;

        if (! $violation) {
            abort(404);
        }

        // Only the recording security officer may open it.
        $security = Auth::guard('security')->user();
        if (! $security || ! $this->recorderMatches($violation, $security)) {
            abort(403);
        }

        $disk = $this->evidenceDisk();

        if (! $evidence->disk_path || ! $disk->exists($evidence->disk_path)) {
            abort(404);
        }

        // Audit the access + record an evidence security event.
        app(EvidenceSecurityService::class)->auditEvidenceAccess(
            'EVIDENCE_VIEWED',
            $evidence,
            'security',
            (int) $security->id,
            $request ?? request(),
        );

        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        return $disk->response(
            $evidence->disk_path,
            $evidence->original_name ?: basename($evidence->disk_path)
        );
    }

    /**
     * Whether the given violation was recorded by this security account.
     */
    private function recorderMatches(Violation $violation, $security): bool
    {
        $label = $this->buildSecurityAccountName($security);
        $legacyEmailLabel = !empty($security?->email) ? 'Security: ' . $security->email : null;
        $legacyIdLabel = 'Security #' . ($security?->id ?? 'Unknown');

        return (string) $violation->recorder_type === 'security'
            && in_array((string) $violation->recorder_name, array_filter([
                $label,
                $legacyEmailLabel,
                $legacyIdLabel,
            ]), true);
    }
}
