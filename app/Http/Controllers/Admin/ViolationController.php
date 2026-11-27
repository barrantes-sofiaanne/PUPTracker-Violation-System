<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

use App\Models\User;
use App\Models\Student;
use App\Models\Violation;
use App\Models\ViolationEvidence;
use App\Models\ViolationType;
use App\Models\ViolationCategory;
use App\Models\DisciplinarySanction;
use App\Models\Program;
use App\Models\Year;
use App\Models\Section;
use App\Models\StudentStatus;
use App\Models\SanctionRequest;
use App\Models\StudentSanctionRecord;
use App\Models\AuditLog;
use App\Models\EvidenceSecurityEvent;
use App\Services\EvidenceSecurityService;
use App\Services\OffenseNumberService;

class ViolationController extends Controller
{
public function create()
{
    return redirect()->route('admin.violations.index');
}

public function index(Request $request)
{
    /*
    |--------------------------------------------------------------------------
    | Management Tab
    |--------------------------------------------------------------------------
    */

 /*
|--------------------------------------------------------------------------
| Management Tab
|--------------------------------------------------------------------------
*/

$students = User::with([
    'studentInfo.program',
    'studentInfo.year',
    'studentInfo.section',
    'studentInfo.studentStatus',
    'violations' => function ($q) {
        $q->effective();
    },
    'role'
])
->whereHas('role', function ($query) {
    $query->where('roles_name', 'Student');
})
->when($request->filled('search'), function ($query) use ($request) {
    $search = '%' . trim($request->input('search')) . '%';
    $query->where(function ($q) use ($search) {
        $q->where('student_number', 'like', $search)
            ->orWhere('first_name', 'like', $search)
            ->orWhere('last_name', 'like', $search)
            ->orWhere('email', 'like', $search)
            ->orWhereHas('studentInfo.program', fn ($p) => $p->where('program_name', 'like', $search));
    });
})
->when($request->filled('program'), function ($query) use ($request) {
    $query->whereHas('studentInfo', fn ($q) => $q->where('program_id', $request->input('program')));
})
->when($request->filled('year'), function ($query) use ($request) {
    $query->whereHas('studentInfo', fn ($q) => $q->where('year_id', $request->input('year')));
})
->when($request->filled('section'), function ($query) use ($request) {
    $query->whereHas('studentInfo', fn ($q) => $q->where('section_id', $request->input('section')));
})
->when($request->filled('student_status'), function ($query) use ($request) {
    $query->whereHas('studentInfo', fn ($q) => $q->where('student_status_id', $request->input('student_status')));
})
->when($request->filled('from_date') || $request->filled('to_date'), function ($query) use ($request) {
    $query->whereHas('violations', function ($q) use ($request) {
        $q->effective();
        if ($request->filled('from_date')) {
            $q->whereDate('violation_date', '>=', $request->input('from_date'));
        }
        if ($request->filled('to_date')) {
            $q->whereDate('violation_date', '<=', $request->input('to_date'));
        }
    });
})
->orderBy('last_name')
->orderBy('first_name')
->paginate(10, ['*'], 'students_page')
->withQueryString();

    /*
    |--------------------------------------------------------------------------
    | Filters
    |--------------------------------------------------------------------------
    */

    $programs = Program::orderBy('program_name')->get();

    $years = Year::orderBy('year')->get();

    $sections = Section::orderBy('section_name')->get();

    $studentStatuses = StudentStatus::orderBy('status_name')->get();

    /*
    |--------------------------------------------------------------------------
    | Configuration
    |--------------------------------------------------------------------------
    */

    $categories = ViolationCategory::with([
    'violationTypes'
])
->orderBy('category_name')
->get();

    $violationTypes = ViolationType::with('violationCategory')
        ->orderBy('violation_type')
        ->get();

    $sanctions = DisciplinarySanction::with('violationType')
        ->orderBy('violation_type_id')
        ->get();

    /*
    |--------------------------------------------------------------------------
    | History
    |--------------------------------------------------------------------------
    */

$violationHistory = Violation::with([
    'student.studentInfo.program',
    'violationType.violationCategory',
    'recorder.adminInfo',
    'evidence',
])
->where(function ($query) {
    // Admin-recorded violations are always retained in History, regardless
    // of legacy status formatting. Other recorder types retain the established
    // pending/confirmed/deleted visibility rules.
    $query->where('recorder_type', 'admin')
        ->orWhereNull('status')
        ->orWhereIn('status', [
            Violation::STATUS_PENDING,
            Violation::STATUS_CONFIRMED,
            Violation::STATUS_DELETED,
        ]);
})    ->latest('violation_date')
    ->when($request->filled('history_search'), function ($query) use ($request) {
        $search = '%' . trim($request->input('history_search')) . '%';
        $query->where(function ($q) use ($search) {
            $q->where('student_number', 'like', $search)
                ->orWhereHas('student', fn ($s) => $s->where('first_name', 'like', $search)->orWhere('last_name', 'like', $search))
                ->orWhereHas('violationType', fn ($v) => $v->where('violation_type', 'like', $search));
        });
    })
    ->when($request->filled('history_from'), fn ($query) => $query->whereDate('violation_date', '>=', $request->input('history_from')))
    ->when($request->filled('history_to'), fn ($query) => $query->whereDate('violation_date', '<=', $request->input('history_to')))
    ->paginate(15, ['*'], 'history_page')
    ->withQueryString();

    /*
    |--------------------------------------------------------------------------
    | Confirmations Tab
    |--------------------------------------------------------------------------
    | The former standalone "Violation Confirmations" page is now the FIRST tab
    | of this Violations page. The query below is the same one the standalone
    | action used — it is only evaluated when that tab is active, so the other
    | tabs do not pay for it.
    |--------------------------------------------------------------------------
    */
    $confirmations = null;

    if ($this->activeViolationsTab($request) === 'confirmations') {
        $confirmations = $this->buildConfirmationsPayload($request);
    }

    return view(
        'admin.violations.index',
        array_merge(
            compact(
                'students',
                'programs',
                'years',
                'sections',
                'studentStatuses',
                'categories',
                'violationTypes',
                'sanctions',
                'violationHistory'
            ),
            $confirmations ?? [],
            ['activeViolationsTab' => $this->activeViolationsTab($request)]
        )
    );
}

    /**
     * The tab keys this page understands. "confirmations" is FIRST by design.
     */
    protected const VIOLATION_TABS = ['confirmations', 'management', 'configuration', 'history'];

    protected function activeViolationsTab(Request $request): string
    {
        $tab = (string) $request->input('tab', '');

        return in_array($tab, self::VIOLATION_TABS, true) ? $tab : 'confirmations';
    }

    /**
     * Build the Violation Confirmations dataset.
     *
     * The queue is the admin REVIEW LIST, so it is ordered by the OLDEST
     * violation date first (ties broken by the record id). That way the first
     * confirmation an administrator sees for a student + violation type is the
     * chronological 1st Offense, never whatever order the database happened to
     * return.
     *
     * The offense filter is applied with the SAME derived sequence the UI
     * displays (see OffenseNumberService::offenseNumberSql()), so filtering by
     * "2nd Offense" can never disagree with the number in the row.
     *
     * Filters: search, from, to, offense, category, violation_type. The queue
     * only ever contains pending rows, so a status filter is not applicable.
     *
     * @return array{violations: \Illuminate\Contracts\Pagination\LengthAwarePaginator, pendingCount: int, highlightId: int, offenseFilter: string}
     */
    protected function buildConfirmationsPayload(Request $request): array
    {
        $offenseFilter = strtolower(trim((string) $request->input('offense', '')));
        if (! in_array($offenseFilter, OffenseNumberService::OFFENSE_FILTERS, true)) {
            $offenseFilter = '';
        }

        $violations = Violation::with([
                'student.studentInfo.program',
                'violationType.violationCategory',
                'recorder.adminInfo',
                'evidence',
            ])
            ->where('status', Violation::STATUS_PENDING)
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = '%' . trim($request->input('search')) . '%';
                $query->where(function ($q) use ($search) {
                    $q->where('student_number', 'like', $search)
                        ->orWhereHas('student', fn ($s) => $s->where('first_name', 'like', $search)->orWhere('last_name', 'like', $search))
                        ->orWhereHas('violationType', fn ($v) => $v->where('violation_type', 'like', $search));
                });
            })
            ->when($request->filled('from'), fn ($query) => $query->whereDate('violation_date', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('violation_date', '<=', $request->input('to')))
            ->when($request->filled('category'), function ($query) use ($request) {
                $query->whereHas(
                    'violationType',
                    fn ($typeQuery) => $typeQuery->where(
                        'violation_category_id',
                        $request->input('category')
                    )
                );
            })
            ->when($request->filled('violation_type'), function ($query) use ($request) {
                $query->where('violation_type_id', $request->input('violation_type'));
            })
            ->when($offenseFilter !== '', function ($query) use ($offenseFilter) {
                $expression = app(OffenseNumberService::class)->offenseNumberSql();

                if ($offenseFilter === '4plus') {
                    $query->whereRaw($expression . ' >= 4');
                } else {
                    $query->whereRaw($expression . ' = ?', [(int) $offenseFilter]);
                }
            })
            // Oldest first: the earliest eligible violation is the 1st Offense.
            ->orderBy('violation_date')
            ->orderBy('violation_id')
            ->paginate(15, ['*'], 'confirmation_page')
            ->withQueryString();

        $pendingCount = Violation::where('status', Violation::STATUS_PENDING)->count();

        // If an admin arrived from a notification (pending_id), keep it flagged
        // so the view can highlight the exact violation.
        $highlightId = $request->integer('pending_id');

        return compact('violations', 'pendingCount', 'highlightId', 'offenseFilter');
    }

    /*
    |--------------------------------------------------------------------------
    | Violation Confirmation Queue
    |--------------------------------------------------------------------------
    |
    | Security-recorded violations start as 'pending' and are sent here for an
    | administrator to confirm or deny before they count toward the student's
    | record. Confirmations are surfaced to admins via the notification bell.
    |--------------------------------------------------------------------------
    */

    /**
     * Backward-compatible entry point for the former standalone
     * "Violation Confirmations" page.
     *
     * That page is now the FIRST tab of the Violations page. This redirects
     * (preserving the existing search/date filters and any notification
     * highlight) so bookmarks, the notification bell and any existing links keep
     * working. No separate implementation is kept alive behind this URL.
     */
    public function confirmations(Request $request)
    {
        $params = ['tab' => 'confirmations'];

        foreach (['search', 'from', 'to', 'offense', 'category', 'violation_type', 'pending_id'] as $key) {
            if ($request->filled($key)) {
                $params[$key] = $request->input($key);
            }
        }

        return redirect()->route('admin.violations.index', $params);
    }

    public function confirmViolation(Violation $violation): \Illuminate\Http\RedirectResponse
    {
        if (strtolower((string) $violation->status) !== Violation::STATUS_PENDING) {
            return back()->withErrors([
                'violation' => 'This violation has already been reviewed.',
            ]);
        }

        $adminId = Auth::guard('admin')->id();

        DB::transaction(function () use ($violation, $adminId): void {
            $violation->update([
                'status' => Violation::STATUS_CONFIRMED,
                'reviewed_by_admin_id' => $adminId,
                'reviewed_at' => now(),
            ]);

            // Confirming keeps the row's chronological position. Recompute the
            // affected student/type sequence (nothing else is touched) so
            // every module reads the same derived labels.
            $this->recalculateOffenseSequence($violation);

            $this->notifyViolationOutcome($violation, 'confirmed');

            AuditLog::create([
                'actor_type' => 'admin',
                'actor_id' => $adminId,
                'action' => 'Confirm Violation',
                'module' => 'Violations',
                'description' => 'Confirmed violation #' . $violation->violation_id . ' for student ' . ($violation->student_number ?? 'unknown') . '.',
            ]);
        });

        return back()->with('success', 'Violation confirmed and added to the student\'s record.');
    }

    public function denyViolation(Request $request, Violation $violation): \Illuminate\Http\RedirectResponse
    {
        if (strtolower((string) $violation->status) !== Violation::STATUS_PENDING) {
            return back()->withErrors([
                'violation' => 'This violation has already been reviewed.',
            ]);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $adminId = Auth::guard('admin')->id();

        // Capture the current sequence BEFORE the row stops counting so any
        // downstream shift can be reported to the administrator.
        $beforeSequence = $this->offenseSequenceSnapshot($violation);

        DB::transaction(function () use ($violation, $adminId, $validated, $beforeSequence): void {
            $violation->update([
                'status' => Violation::STATUS_DENIED,
                'reviewed_by_admin_id' => $adminId,
                'reviewed_at' => now(),
                'review_remarks' => trim($validated['reason']),
            ]);

            // Excluding the denied row shifts later matching pending/confirmed
            // rows down by one. Recalculate the affected student/type only.
            $this->recalculateOffenseSequence($violation, $beforeSequence);

            $this->notifyViolationOutcome($violation, 'denied');

            AuditLog::create([
                'actor_type' => 'admin',
                'actor_id' => $adminId,
                'action' => 'Deny Violation',
                'module' => 'Violations',
                'description' => 'Denied violation #' . $violation->violation_id . ' for student ' . ($violation->student_number ?? 'unknown') . '. Reason: ' . trim($validated['reason']) . '.',
            ]);
        });

        return back()->with('success', 'Violation denied. The student and recording security officer were notified.');
    }

    /**
     * Notify the student and security about the admin decision on a
     * security-recorded violation.
     */
    private function notifyViolationOutcome(Violation $violation, string $decision): void
    {
        $student = $violation->student;
        $studentName = $student
            ? trim(($student->first_name ?? '') . ' ' . ($student->last_name ?? ''))
            : ($violation->student_number ?? 'a student');
        $violationLabel = $violation->violationType?->violation_type
            ?? $violation->violation_type_display
            ?? 'the recorded violation';
        $reason = trim((string) $violation->review_remarks);

        // The officer/admin who recorded this violation. Security recordings
        // carry recorded_by_security_id and are notified through the SECURITY
        // inbox; an OSS Staff recording is an ADMIN-actor row (recorder_type =
        // 'admin' with recorder_id set) and is notified through the ADMIN
        // inbox addressed to that exact OSS Staff account, under its own target
        // type so the notification opens inside the OSS Staff module.
        $recordingSecurityId = (int) ($violation->recorded_by_security_id ?? 0);
        $recordingSecurityId = $recordingSecurityId > 0 ? $recordingSecurityId : null;

        // An OSS Staff recording: an admin-actor row recorded by a SPECIFIC
        // admin account. Rows recorded directly by a plain administrator from
        // the admin module are also admin-actor; the OSS Staff rows are the
        // ones whose recorder is an OSS Staff account.
        $recordingOssStaffId = null;
        if ($recordingSecurityId === null
            && (string) $violation->recorder_type === 'admin'
            && (int) ($violation->recorder_id ?? 0) > 0) {
            $recorder = $violation->recorder;
            if ($recorder instanceof \App\Models\Admin && $recorder->isOssStaff()) {
                $recordingOssStaffId = (int) $violation->recorder_id;
            }
        }

        if ($decision === 'confirmed') {
            // Notify the student their violation is now on their record.
            try {
                app(\App\Services\StudentNotificationService::class)->create(
                    studentNumber: $violation->student_number,
                    type: 'violation',
                    title: 'Violation Confirmed',
message: 'Your recorded violation (' . $violationLabel . ') has been confirmed and added to your Student Record. Please check your PUPTracker account regularly for any disciplinary sanction related to this violation. If the system requires a disciplinary sanction request, you may submit the request through your account for proper review.',                    targetType: 'violation',
                    targetId: (int) $violation->violation_id,
                    userId: (int) $student->user_id,
                );
            } catch (\Throwable $e) {
                // Notification must never break the confirmation.
            }

            // Notify the recording SECURITY officer of the confirmation.
            try {
                app(\App\Services\SecurityNotificationService::class)->create(
                    type: 'violation',
                    title: 'Violation Confirmed',
                    message: 'The violation you recorded for ' . $studentName . ' (' . ($violation->student_number ?? '') . ') has been confirmed by the administrator.',
                    targetType: 'violation',
                    targetId: (int) $violation->violation_id,
                    recipientId: $recordingSecurityId,
                );
            } catch (\Throwable $e) {
                // Notification must never break the confirmation.
            }

            $this->notifyOssStaffRecorder($violation, $recordingOssStaffId, 'confirmed', $studentName, $violationLabel, $reason);
        } else {
            // Notify the student their violation was denied with the reason.
            try {
                app(\App\Services\StudentNotificationService::class)->create(
                    studentNumber: $violation->student_number,
                    type: 'violation',
                    title: 'Violation Rejected',
                    message: 'A recorded violation (' . $violationLabel . ') was not confirmed. Reason: ' . ($reason !== '' ? $reason : 'No reason provided.'),
                    targetType: 'violation',
                    targetId: (int) $violation->violation_id,
                );
            } catch (\Throwable $e) {
                // Notification must never break the denial.
            }

            // Notify the recording SECURITY officer of the denial + reason.
            try {
                app(\App\Services\SecurityNotificationService::class)->create(
                    type: 'violation',
                    title: 'Violation Rejected',
                    message: 'The violation you recorded for ' . $studentName . ' (' . ($violation->student_number ?? '') . ') was denied by the administrator. Reason: ' . ($reason !== '' ? $reason : 'No reason provided.'),
                    targetType: 'violation',
                    targetId: (int) $violation->violation_id,
                    recipientId: $recordingSecurityId,
                );
            } catch (\Throwable $e) {
                // Notification must never break the denial.
            }

            $this->notifyOssStaffRecorder($violation, $recordingOssStaffId, 'denied', $studentName, $violationLabel, $reason);
        }
    }

    /**
     * Notify the OSS Staff member who recorded this violation about the
     * administrator's decision.
     *
     * The notification is an ADMIN notification addressed to that exact OSS
     * Staff account, tagged `oss_staff_violation` so opening it returns the
     * member to the OSS Staff module (their tracker, or the student record for
     * a confirmed row) rather than the Admin confirmation queue.
     *
     * No-op for rows that were not recorded by an OSS Staff account.
     */
    private function notifyOssStaffRecorder(
        Violation $violation,
        ?int $ossStaffId,
        string $decision,
        string $studentName,
        string $violationLabel,
        string $reason
    ): void {
        if ($ossStaffId === null) {
            return;
        }

        try {
            $confirmed = $decision === 'confirmed';

            app(\App\Services\AdminNotificationService::class)->create(
                type: 'violation',
                title: $confirmed ? 'Violation Confirmed' : 'Violation Rejected',
                message: $confirmed
                    ? 'The violation you recorded for ' . $studentName . ' (' . ($violation->student_number ?? '') . ') has been confirmed by the administrator.'
                    : 'The violation you recorded for ' . $studentName . ' (' . ($violation->student_number ?? '') . ') was denied by the administrator. Reason: ' . ($reason !== '' ? $reason : 'No reason provided.'),
                targetType: 'oss_staff_violation',
                targetId: (int) $violation->violation_id,
                recipientId: $ossStaffId,
                recipientType: \App\Models\AdminNotification::AUDIENCE_ADMIN,
            );
        } catch (\Throwable $e) {
            // Notification must never break the decision.
        }
    }

public function show(Request $request, string $student_number)
{
    $student = User::with([
        'studentInfo.program',
        'studentInfo.year',
        'studentInfo.section',
        'studentInfo.studentStatus',
    ])
    ->where('student_number', $student_number)
    ->orWhere('user_id', ctype_digit($student_number) ? (int) $student_number : -1)
    ->firstOrFail();

    $allViolations = Violation::with([
        'violationType.violationCategory',
        'recorder.adminInfo'
    ])
    ->where('user_id', $student->user_id)
    ->effective()
    ->latest('violation_date')
    ->get();

$individualViolations = Violation::with([
    'violationType.violationCategory',
    'recorder.adminInfo',
'recordingSecurity.securityInfo',
])
    ->where('user_id', $student->user_id)
    ->effective()
    ->latest('violation_date')
    ->paginate(10)
    ->appends($request->query());

    $totalViolations = $allViolations->count();

    $majorViolations = $allViolations
        ->filter(fn ($v) => strcasecmp(
            optional(optional($v->violationType)->violationCategory)->category_name ?? '',
            'Major'
        ) === 0)
        ->count();

    $sanctionRecords = StudentSanctionRecord::where('student_number', $student->student_number)->get();

    $outstandingSanctions = $sanctionRecords
        ->filter(fn ($r) => strcasecmp((string) $r->status, 'Completed') !== 0)
        ->count();

    $completedSanctions = $sanctionRecords
        ->filter(fn ($r) => strcasecmp((string) $r->status, 'Completed') === 0)
        ->count();

    // Group by the RESOLVED violation type, not the raw column.
    //
    // `violation_tbl.violation_type` holds the type ID for records created by
    // the app, but legacy rows can hold the type NAME. Grouping on the raw column
    // therefore split one violation type into two rows (one keyed by "10", one by
    // "No ID") and the table printed the bare number. Keying on the relationship's
    // ID keeps one row per real type and lets every row print its human-readable
    // name.
    //
    // A legacy name-valued row has no relationship match, so its raw value is
    // resolved back to a type ID through the same violation_type_tbl data.
    $typeIdByName = ViolationType::query()
        ->pluck('violation_type_id', 'violation_type')
        ->mapWithKeys(fn ($id, $name) => [mb_strtolower(trim((string) $name)) => $id])
        ->all();

    $summary = $allViolations
        ->groupBy(function (Violation $violation) use ($typeIdByName) {
            $resolvedType = $violation->resolvedViolationType();
            $resolvedId = $resolvedType?->violation_type_id;

            if ($resolvedId !== null) {
                return 'type:' . $resolvedId;
            }

            $raw = mb_strtolower(trim((string) (
                $resolvedType?->violation_type
                ?? $violation->attributes['violation_type']
                ?? ''
            )));

            return isset($typeIdByName[$raw])
                ? 'type:' . $typeIdByName[$raw]
                : 'raw:' . $raw;
        })
        ->map(function ($records) use ($student) {
            $latest = $records->first();
            $latestType = $latest->resolvedViolationType();

            // Use the centralized derived sequence for the whole student/type,
            // not just the confirmed rows loaded on this page. Pending rows
            // reserve their slot, so a matching pending violation is reflected
            // in the offense label while the history table stays confirmed-only.
            $resolvedTypeId = $latestType?->violation_type_id;

            $offenseLevel = $resolvedTypeId !== null
                ? app(\App\Services\OffenseNumberService::class)->currentOffenseLevel(
                    (int) $student->user_id,
                    (int) $resolvedTypeId
                )
                : $this->formatOffenseLevel($records->count());

            $disciplinarySanction = DisciplinarySanction::where(
                    'violation_type_id',
                    $latestType?->violation_type_id
                )
                ->where('offense_level', $offenseLevel)
                ->first();

            $sanctionRequest = SanctionRequest::where(
                    'student_number',
                    $latest->student_number
                )
                ->where(
                    'violation_type_id',
                    $latestType?->violation_type_id
                )
                ->latest('request_date')
                ->first();

            $sanctionText = strtolower((string) ($disciplinarySanction?->disciplinary_sanction ?? ''));
            $violationStatus = str_contains($sanctionText, 'warning')
                ? 'Warning'
                : ($disciplinarySanction ? 'Sanction' : 'Unknown');

            return [
                'category' => optional($latestType?->violationCategory)->category_name,
                // Human-readable type name. The `violation_type` column stores the
                // TYPE ID, so printing it directly showed numbers like "10"/"24".
                // The resolved relationship is the source of truth; Security
                // does the same.
                'violation_type' => $latestType?->violation_type
                    ?? ($latest->attributes['violation_type'] ?? '-'),
                'offense_level' => $offenseLevel,
                'status' => $sanctionRequest?->status ?? 'No Request',
                'violation_status' => $violationStatus,
                'remarks' => $records->count() > 1
                    ? '(Multiple instances - see log)'
                    : ($latest->description ?: '-'),
                'sanction' => $disciplinarySanction?->disciplinary_sanction ?? '-',
                'total' => $records->count(),
            ];
        })
        ->values();

    $source = (string) ($request->input('source') ?: 'results');
    $backRoute = match ($source) {
        'dashboard' => route('admin.dashboard'),
        'reports' => route('admin.reports'),
        default => route('admin.violations.index', $request->except(['student_number', 'source'])),
    };
    $backLabel = match ($source) {
        'dashboard' => 'Back to Dashboard',
        'reports' => 'Back to Reports',
        default => 'Back to Results',
    };

    // When this endpoint is used by the student-history modal (AJAX), return a
    // clean partial WITHOUT the admin layout so it renders inside the modal.
    if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
        return view(
            'admin.violations.partials.student-record-partial',
            compact(
                'student',
                'summary',
                'individualViolations',
                'totalViolations',
                'majorViolations',
                'outstandingSanctions',
                'completedSanctions'
            )
        );
    }

    return view(
        'admin.violations.show',
        compact(
            'student',
            'summary',
            'individualViolations',
            'totalViolations',
            'majorViolations',
            'outstandingSanctions',
            'completedSanctions',
            'backRoute',
            'backLabel'
        )
    );
}
    /*
    |--------------------------------------------------------------------------
    | Search Student
    |--------------------------------------------------------------------------
    */
public function searchStudents(Request $request)
{
    $search = $request->search;

    $students = User::with([
        'studentInfo.program',
        'studentInfo.year',
        'studentInfo.section',
        'studentInfo.studentStatus'
    ])
    ->whereHas('role', function ($query) {
        $query->where('roles_name', 'Student');
    })
    ->where(function ($query) use ($search) {

        $query->where('student_number', 'like', "%{$search}%")
              ->orWhere('first_name', 'like', "%{$search}%")
              ->orWhere('last_name', 'like', "%{$search}%");

    })
    ->limit(10)
    ->get();

    return response()->json($students);
}
   public function searchStudent(Request $request): JsonResponse
{
    $keyword = trim($request->search);

    if ($keyword === '') {
        return response()->json([]);
    }

    $students = User::with([
        'studentInfo.program',
        'studentInfo.year',
        'studentInfo.section',
        'studentInfo.studentStatus',
    ])
    ->whereHas('role', function ($query) {
        $query->where('roles_name', 'Student');
    })
    ->where(function ($query) use ($keyword) {

        $query->where('student_number', 'like', "%{$keyword}%")
              ->orWhere('first_name', 'like', "%{$keyword}%")
              ->orWhere('last_name', 'like', "%{$keyword}%");

    })
    ->orderBy('last_name')
    ->limit(10)
    ->get();

    return response()->json($students->map(function (User $student) {
        return [
            'user_id' => $student->user_id,
            'student_number' => $student->student_number,
            'first_name' => $student->first_name,
            'last_name' => $student->last_name,
            // PUPTracker ACCOUNT status. The client uses this to warn/warn-off
            // recording a violation for an inactive student BEFORE the wizard
            // ends. It is a UI convenience only — the server re-checks the same
            // rule in StoreViolationRequest and rejects the submission either way.
            'account_active' => $student->isActiveAccount(),
            'program' => [
                'program_name' => $student->studentInfo?->program?->program_name,
            ],
            'year' => [
                'year_name' => $student->studentInfo?->year?->year,
            ],
            // Resolve the section from the student's linked section record.
            // The explicit lookup is a fallback for older/inconsistent Eloquent
            // relationship hydration while keeping the existing JSON shape.
            'section' => [
                'section_name' => $student->studentInfo?->section?->section_name
                    ?? Section::where('section_id', $student->studentInfo?->section_id)
                        ->value('section_name'),
            ],
            // Also expose a flat value for any client code that expects it.
            'section_name' => $student->studentInfo?->section?->section_name
                ?? Section::where('section_id', $student->studentInfo?->section_id)
                    ->value('section_name'),
        ];
    }));
}

    public function getViolationTypes(Request $request): JsonResponse
    {
        $categoryId = $request->integer('category_id');

        if (!$categoryId) {
            return response()->json([]);
        }

        $types = ViolationType::where('violation_category_id', $categoryId)
            ->orderBy('violation_type')
            ->get([
                'violation_type_id',
                'violation_type',
            ]);

        return response()->json($types);
    }


    /*
    |--------------------------------------------------------------------------
    | Preview Violation
    |--------------------------------------------------------------------------
    */

    public function previewViolation(Request $request): JsonResponse
    {
        $request->validate([
    'user_id' => ['required', 'integer', 'exists:users_tbl,user_id'],
    'violation_type_id' => ['required', 'integer'],
]);

$violationType = ViolationType::with('violationCategory')
    ->find($request->violation_type_id);
        if (!$violationType) {

            return response()->json([
                'message' => 'Violation type not found.'
            ], 404);

        }

        /*
        |--------------------------------------------------------------------------
        | Determine Current Offense
        |--------------------------------------------------------------------------
        |
        | Delegated to the centralized OffenseNumberService so Admin, Security,
        | and OSS Staff always agree. Pending violations reserve their slot;
        | denied/deleted ones do not.
        */

        $offenseLevel = app(\App\Services\OffenseNumberService::class)->nextOffenseLevel(
            (int) $request->user_id,
            (int) $violationType->violation_type_id
        );

        /*
        |--------------------------------------------------------------------------
        | Find Matching Sanction
        |--------------------------------------------------------------------------
        */

        $sanction = DisciplinarySanction::where(
                'violation_type_id',
                $violationType->violation_type_id
            )
            ->where(
                'offense_level',
                $offenseLevel
            )
            ->first();

        // Fall back to the first configured sanction for this violation type so
        // the preview sanction section is always filled when one exists.
        if (! $sanction) {
            $sanction = DisciplinarySanction::where(
                'violation_type_id',
                $violationType->violation_type_id
            )
            ->orderBy('disciplinary_sanction_id')
            ->first();
        }

        return response()->json([

           'category' => optional(
    $violationType->violationCategory
)->category_name,

            'violation_type' => $violationType->violation_type,

            'severity_level' => $violationType->severity_level,

            'offense_level' => $offenseLevel,

            'sanction' => $sanction
                ? $sanction->disciplinary_sanction
                : 'No disciplinary sanction configured.'

        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Helper : Format Offense Level
    |--------------------------------------------------------------------------
    */

    public static function formatOffenseLevelStatic(int $count): string
    {
        return app(\App\Services\OffenseNumberService::class)->formatOffenseLevel($count);
    }

    private function formatOffenseLevel(int $count): string
    {
        return self::formatOffenseLevelStatic($count);
    }

    /**
     * Current offense-number map for the violation's student + type.
     *
     * @return array<int, int>  [violation_id => offense_number]
     */
    private function offenseSequenceSnapshot(Violation $violation): array
    {
        $userId = (int) ($violation->user_id ?? 0);
        $typeId = (int) ($violation->violation_type_id ?? 0);

        if ($userId <= 0 || $typeId <= 0) {
            return [];
        }

        return app(\App\Services\OffenseNumberService::class)
            ->offenseNumbersFor($userId, $typeId);
    }

    /**
     * Recalculate the offense labels of the affected student + violation type
     * after an administrator decision.
     *
     * Offense numbers are derived (see OffenseNumberService), so this does not
     * write a snapshot — it forces the current sequence to be recomputed for
     * this student/type only, which is what every module then reads.
     *
     * When an already-confirmed violation's offense level would move and it
     * already has a disciplinary sanction record, that record is NOT rewritten.
     * Its history is preserved and the shift is written to the audit trail so
     * an administrator can review the completed decision.
     *
     * @param  array<int, int>  $beforeSequence
     * @return array<int, int>
     */
    private function recalculateOffenseSequence(
        Violation $violation,
        array $beforeSequence = []
    ): array {
        $userId = (int) ($violation->user_id ?? 0);
        $typeId = (int) ($violation->violation_type_id ?? 0);

        if ($userId <= 0 || $typeId <= 0) {
            return [];
        }

        $service = app(\App\Services\OffenseNumberService::class);
        $afterSequence = $service->offenseNumbersFor($userId, $typeId);

        if ($beforeSequence !== []) {
            $this->flagShiftedSanctions($beforeSequence, $afterSequence, $service);
        }

        return $afterSequence;
    }

    /**
     * Flag confirmed violations whose derived offense level changed while a
     * sanction record already exists. The sanction itself is left intact.
     *
     * @param  array<int, int>  $before
     * @param  array<int, int>  $after
     */
    private function flagShiftedSanctions(
        array $before,
        array $after,
        \App\Services\OffenseNumberService $service
    ): void {
        foreach ($after as $violationId => $newPosition) {
            $oldPosition = $before[$violationId] ?? null;

            if ($oldPosition === null || $oldPosition === $newPosition) {
                continue;
            }

            $hasSanctionRecord = StudentSanctionRecord::query()
                ->where('violation_id', $violationId)
                ->exists();

            if (! $hasSanctionRecord) {
                continue;
            }

            try {
                AuditLog::create([
                    'actor_type' => 'admin',
                    'actor_id' => Auth::guard('admin')->id(),
                    'action' => 'Offense Level Recalculated',
                    'module' => 'Violations',
                    'description' => sprintf(
                        'Violation #%d offense level changed from %s to %s after an administrator decision. The existing sanction record was preserved for administrator review.',
                        (int) $violationId,
                        $service->formatOffenseLevel((int) $oldPosition),
                        $service->formatOffenseLevel((int) $newPosition)
                    ),
                ]);
            } catch (\Throwable $e) {
                // Never let accountability logging break the decision.
                report($e);
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | CONTINUES IN PART 3
    |--------------------------------------------------------------------------
    */
        /*
    |--------------------------------------------------------------------------
    | Store Violation
    |--------------------------------------------------------------------------
    */

public function store(\App\Http\Requests\StoreViolationRequest $request): JsonResponse
{
    $validated = $request->validated();

    // Administrator recordings must include an explicit student-identity
    // confirmation. Security and OSS Staff use the same FormRequest, so this
    // rule is intentionally enforced here rather than globally in the request.
    if (! $request->boolean('identity_confirmed')) {
        return response()->json([
            'message' => 'You must confirm the student identity before recording the violation.',
        ], 422);
    }

    $violationType = ViolationType::findOrFail(
        $validated['violation_type_id']
    );

    DB::beginTransaction();

    try {
            $student = User::findOrFail((int) $validated['user_id']);

            // Serialize simultaneous recordings for the same student so two
            // requests cannot derive the same offense position.
            app(\App\Services\OffenseNumberService::class)->lockStudent((int) $student->user_id);

            /*
            |--------------------------------------------------------------------------
            | Prevent Duplicate Records
            |--------------------------------------------------------------------------
            */



$exists = Violation::where('user_id', $student->user_id)
    ->where('violation_type_id', $violationType->violation_type_id)
                ->whereDate(
                    'violation_date',
                    date(
                        'Y-m-d',
                        strtotime($validated['violation_date'])
                    )
                )
                // A denied violation should not block re-recording the same real
                // incident; pending + confirmed still block a same-day duplicate.
                ->notDenied()
                ->exists();

            if ($exists) {

                DB::rollBack();

                return response()->json([

                    'message' =>
                        'A violation of this type has already been recorded for this student today.'

                ], 422);

            }

            /*
            |--------------------------------------------------------------------------
            | Create Violation
            |--------------------------------------------------------------------------
            */

            $admin = Auth::guard('admin')->user();
            $adminName = trim(
                (string) ($admin?->adminInfo?->firstname ?? '') . ' ' .
                (string) ($admin?->adminInfo?->middlename ?? '') . ' ' .
                (string) ($admin?->adminInfo?->lastname ?? '')
            );

            $violation = Violation::create([

                'user_id' => $student->user_id,
                'student_number' => $student->student_number,

                'violation_type_id' => $violationType->violation_type_id,

                'violation_date' => $validated['violation_date'],

                'description' => trim(
                    $validated['description']
                ),

'recorder_id' => Auth::guard('admin')->id(),
                'recorder_type' => 'admin',
                'recorder_name' => $adminName !== ''
                    ? $adminName
                    : ($admin?->email ? 'Admin: ' . $admin->email : 'Admin #' . Auth::guard('admin')->id()),
                // Admin-recorded violations are recorded by the confirming
                // authority itself, so they count immediately.
                'status' => Violation::STATUS_CONFIRMED,
                'reviewed_by_admin_id' => Auth::guard('admin')->id(),
                'reviewed_at' => now(),
            ]);

            // Persist any captured/uploaded evidence using the existing
            // evidence subsystem (private storage, evidence codes, security
            // events). Runs inside the same transaction so a storage failure
            // rolls back the violation too.
            $captureSessionId = $request->input('capture_session_id')
                ? (string) $request->input('capture_session_id')
                : null;
            $this->storeEvidence($violation, $request, (int) Auth::guard('admin')->id(), $captureSessionId);

            DB::commit();

            // Notify the student about the recorded violation (target-aware).
            try {
                app(\App\Services\StudentNotificationService::class)->create(
                    studentNumber: (string) ($student->student_number ?? ''),
                    type: 'violation',
                    title: 'Violation Notice',
                    message: 'A violation has been recorded against your record: ' . $violationType->violation_type . '. Please review your Student Record.',
                    targetType: 'violation',
                    targetId: (int) $violation->violation_id,
                );
            } catch (\Throwable $e) {
                // Notification must never break the violation recording.
            }

            return response()->json([
                'success' => true,
                'message' => 'Violation recorded successfully.',
                'data' => $violation,
            ]);

        } catch (\Throwable $exception) {
    Log::error('Admin violation store failed', [
        'student_number' => $request->input('student_number'),
        'error' => $exception->getMessage(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
    ]);

return \App\Support\Responses::unexpectedError();
}
    }

    /**
     * Store uploaded/captured evidence for an admin-recorded violation,
     * privately, with a unique evidence code and evidence security event.
     * Runs inside the caller's DB transaction.
     */
    private function storeEvidence(Violation $violation, Request $request, int $adminId, ?string $captureSessionId = null): void
    {
        $evidenceFiles = $request->allFiles()['evidence'] ?? [];

        if ($evidenceFiles === []) {
            return;
        }

        $captureMethods = (array) $request->input('capture_methods', []);
        $capturedAts = (array) $request->input('captured_ats', []);
        $evidenceService = app(EvidenceSecurityService::class);

        if ($evidenceFiles !== []) {
            // Production guard: refuse to write confidential evidence to an
            // ephemeral filesystem when object storage is misconfigured.
            \App\Support\Storage\EvidenceDisk::assertWritable();
        }

        foreach ($evidenceFiles as $index => $evidenceFile) {
            $evidenceCode = $evidenceService->nextEvidenceCode();
            $directory = 'violation-evidence/' . $violation->violation_id;
            $extension = strtolower($evidenceFile->getClientOriginalExtension());
            // Server-side filename derived from the unique evidence code — the
            // original filename is never trusted or used on disk.
            $filename = $evidenceCode . '.' . ($extension !== '' ? $extension : 'bin');
            $disk = $evidenceService->evidenceDisk();

            $isCamera = strcasecmp((string) ($captureMethods[$index] ?? ''), 'camera') === 0;
            $capturedAt = isset($capturedAts[$index]) && $capturedAts[$index] !== ''
                ? \Carbon\Carbon::parse($capturedAts[$index])
                : null;

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
                'uploader_type' => 'admin',
                'uploader_id' => $adminId,
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
                // The row was created moments ago inside the caller's
                // transaction, so re-throwing rolls it back. Remove any object
                // that did land so no unreferenced confidential file survives.
                try {
                    $disk->delete($storedPath);
                } catch (\Throwable $ignored) {
                    // Best-effort cleanup only.
                }

                throw new \RuntimeException('Unable to store evidence file.');
            }

            $storedSize = \App\Support\Storage\EvidenceDisk::size($storedPath);

            $evidence->update([
                'disk_path' => $storedPath,
                'file_size' => $storedSize ?? $evidenceFile->getSize(),
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
                'admin',
                $adminId,
                $captureSessionId,
            );
        }
    }

    public function history(Request $request)
{
    $violations = Violation::with([
        'student.studentInfo.program',
        'violationType.violationCategory',
        'recorder.adminInfo'
    ])
->where(function ($query) {
    // Admin-recorded violations are always retained in History, regardless
    // of legacy status formatting. Other recorder types retain the established
    // pending/confirmed/deleted visibility rules.
    $query->where('recorder_type', 'admin')
        ->orWhereNull('status')
        ->orWhereIn('status', [
            Violation::STATUS_PENDING,
            Violation::STATUS_CONFIRMED,
            Violation::STATUS_DELETED,
        ]);
})    ->when($request->student, function ($query) use ($request) {
        $query->whereHas('student', function ($q) use ($request) {
            $q->where('student_number', 'like', "%{$request->student}%")
              ->orWhere('first_name', 'like', "%{$request->student}%")
              ->orWhere('last_name', 'like', "%{$request->student}%");
        });
    })
    ->latest('violation_date')
    ->paginate(15, ['*'], 'history_page')
    ->withQueryString();

    return view(
        'admin.violations.history',
        compact('violations')
    );
}

    /**
     * Record an evidence security event from the admin record-violation
     * camera capture flow (mirror of the security portal's endpoint, but
     * admin-guarded). These are detection/deterrence indicators only.
     */
    public function logEvidenceSecurityEvent(Request $request): JsonResponse
    {
        $admin = Auth::guard('admin')->user();
        if (! $admin) {
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
            (int) $admin->id,
            $captureSessionId,
        );

        return response()->json(['success' => true]);
    }

    /**
     * Serve a violation evidence file from private storage to an admin.
     *
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function downloadEvidence(ViolationEvidence $evidence, Request $request)
    {
        $violation = $evidence->violation;

        if (! $violation) {
            abort(404);
        }

        $adminId = (int) Auth::guard('admin')->id();

        // A present violation but an absent/older-format storage key is handled
        // by the shared responder, which returns a controlled error rather than
        // a raw 404 page. Storage stays private — the file is streamed.
        if (! $evidence->disk_path) {
            return \App\Services\PrivateFileResponder::stream(
                'evidence',
                null,
                $evidence->original_name,
                ['evidence_id' => $evidence->evidence_id ?? null]
            );
        }

        // Audit the access + record an evidence security event.
        app(EvidenceSecurityService::class)->auditEvidenceAccess(
            'EVIDENCE_VIEWED',
            $evidence,
            'admin',
            $adminId,
            $request,
        );

        return \App\Services\PrivateFileResponder::stream(
            'evidence',
            $evidence->disk_path,
            $evidence->original_name,
            ['evidence_id' => $evidence->evidence_id ?? null]
        );
    }

    /**
     * Return the security-event timeline for an evidence row (admin review).
     * Events are detection indicators that require administrative review —
     * they never assert a screenshot "confirmed" occurred.
     */
    public function evidenceEvents(ViolationEvidence $evidence, Request $request): JsonResponse
    {
        $adminId = (int) Auth::guard('admin')->id();

        $events = $evidence->securityEvents()
            ->orderBy('event_id')
            ->get()
            ->map(fn ($event) => [
                'id' => $event->event_id,
                'type' => $event->event_type,
                'severity' => $event->severity,
                'created_at' => optional($event->created_at)->format('M d, Y h:i A'),
                'ip' => $event->ip_address,
                'user_agent' => $event->user_agent,
                'meta' => $event->meta,
                'capture_session_id' => $event->capture_session_id,
            ]);

        // Viewing the security timeline is itself an audited evidence access.
        app(EvidenceSecurityService::class)->auditEvidenceAccess(
            'EVIDENCE_VIEWED',
            $evidence,
            'admin',
            $adminId,
            $request,
        );

        return response()->json([
            'success' => true,
            'evidence' => [
                'code' => $evidence->evidence_code,
                'student' => $evidence->violation?->student_number,
                'captured_at' => optional($evidence->captured_at)->format('M d, Y h:i A'),
                'method' => $evidence->capture_method,
                // server_watermarked is the TRUSTED flag (the client `watermarked`
                // flag alone is not proof a watermark exists on the stored image).
                'watermarked' => (bool) $evidence->server_watermarked,
                'sha256' => $evidence->sha256_hash,
                'capture_session_id' => $evidence->capture_session_id,
            ],
            'events' => $events,
        ]);
    }

}