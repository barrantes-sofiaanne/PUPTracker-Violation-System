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
use App\Services\EvidenceSecurityService;

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
        'recorder.adminInfo'
    ])
    ->notDenied()
    ->latest('violation_date')
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

    return view(
        'admin.violations.index',
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
        )
    );
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

    public function confirmations(Request $request)
    {
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
            ->latest('violation_date')
            ->paginate(15, ['*'], 'confirmation_page')
            ->withQueryString();

        $pendingCount = Violation::where('status', Violation::STATUS_PENDING)->count();

        // If an admin arrived from a notification (pending_id), keep it flagged
        // so the view can highlight the exact violation.
        $highlightId = $request->integer('pending_id');

        return view('admin.violations.confirmations', compact('violations', 'pendingCount', 'highlightId'));
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

        DB::transaction(function () use ($violation, $adminId, $validated): void {
            $violation->update([
                'status' => Violation::STATUS_DENIED,
                'reviewed_by_admin_id' => $adminId,
                'reviewed_at' => now(),
                'review_remarks' => trim($validated['reason']),
            ]);

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

        // The security officer who recorded this violation. New recordings carry
        // recorded_by_security_id; legacy rows (null) keep the notification a
        // broadcast so no officer is silently missed.
        $recordingSecurityId = (int) ($violation->recorded_by_security_id ?? 0);
        $recordingSecurityId = $recordingSecurityId > 0 ? $recordingSecurityId : null;

        if ($decision === 'confirmed') {
            // Notify the student their violation is now on their record.
            try {
                app(\App\Services\StudentNotificationService::class)->create(
                    studentNumber: $violation->student_number,
                    type: 'violation',
                    title: 'Violation Confirmed',
                    message: 'Your recorded violation (' . $violationLabel . ') has been confirmed and added to your Student Record.',
                    targetType: 'violation',
                    targetId: (int) $violation->violation_id,
                );
            } catch (\Throwable $e) {
                // Notification must never break the confirmation.
            }

            // Notify the recording security officer of the confirmation.
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

            // Notify the recording security officer of the denial + reason.
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
    ->firstOrFail();

    $allViolations = Violation::with([
        'violationType.violationCategory',
        'recorder.adminInfo'
    ])
    ->where('student_number', $student_number)
    ->effective()
    ->latest('violation_date')
    ->get();

    $individualViolations = Violation::with([
        'violationType.violationCategory',
        'recorder.adminInfo'
    ])
    ->where('student_number', $student_number)
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

    $sanctionRecords = StudentSanctionRecord::where('student_number', $student_number)->get();

    $outstandingSanctions = $sanctionRecords
        ->filter(fn ($r) => strcasecmp((string) $r->status, 'Completed') !== 0)
        ->count();

    $completedSanctions = $sanctionRecords
        ->filter(fn ($r) => strcasecmp((string) $r->status, 'Completed') === 0)
        ->count();

    $summary = $allViolations
        ->groupBy('violation_type')
        ->map(function ($records) {
            $latest = $records->first();
            $offenseLevel = $this->formatOffenseLevel($records->count());

            $disciplinarySanction = DisciplinarySanction::where(
                    'violation_type_id',
                    optional($latest->violationType)->violation_type_id
                )
                ->where('offense_level', $offenseLevel)
                ->first();

            $sanctionRequest = SanctionRequest::where(
                    'student_number',
                    $latest->student_number
                )
                ->where(
                    'violation_type_id',
                    optional($latest->violationType)->violation_type_id
                )
                ->latest('request_date')
                ->first();

            $sanctionText = strtolower((string) ($disciplinarySanction?->disciplinary_sanction ?? ''));
            $violationStatus = str_contains($sanctionText, 'warning')
                ? 'Warning'
                : ($disciplinarySanction ? 'Sanction' : 'Unknown');

            return [
                'category' => optional(optional($latest->violationType)->violationCategory)->category_name,
                'violation_type' => $latest->violation_type,
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
            'student_number' => $student->student_number,
            'first_name' => $student->first_name,
            'last_name' => $student->last_name,
            'program' => [
                'program_name' => $student->studentInfo?->program?->program_name,
            ],
            'year' => [
                'year_name' => $student->studentInfo?->year?->year,
            ],
            'section' => [
                'section_name' => $student->studentInfo?->section?->section_name,
            ],
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
    'student_number' => ['required', 'string'],
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
        | Count Previous Offenses
        |--------------------------------------------------------------------------
        */

$previousCount = Violation::where(
        'student_number',
        $request->student_number
    )
    ->where(function ($query) use ($violationType) {
        $query->where('violation_type', (string) $violationType->violation_type_id)
            ->orWhere('violation_type', $violationType->violation_type);
    })
    ->effective()
    ->count();

        /*
        |--------------------------------------------------------------------------
        | Determine Current Offense
        |--------------------------------------------------------------------------
        */

        $currentOffenseNumber = $previousCount + 1;

        $offenseLevel = $this->formatOffenseLevel(
            $currentOffenseNumber
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

    private function formatOffenseLevel(int $count): string
    {
        return self::formatOffenseLevelStatic($count);
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

public function store(Request $request): JsonResponse
{
    $validated = $request->validate([

        'student_number' => [
            'required',
            'exists:users_tbl,student_number'
        ],

        'violation_type_id' => [
            'required',
            'exists:violation_type_tbl,violation_type_id'
        ],

        'violation_date' => [
            'required',
            'date'
        ],

        'description' => [
            'required',
            'string',
            'max:1000'
        ],

    ]);

    $violationType = ViolationType::findOrFail(
        $validated['violation_type_id']
    );

    DB::beginTransaction();

    try {

            /*
            |--------------------------------------------------------------------------
            | Prevent Duplicate Records
            |--------------------------------------------------------------------------
            */

            $exists = Violation::where(
                    'student_number',
                    $validated['student_number']
                )
                ->where(function ($query) use ($violationType) {
                    $query->where('violation_type', (string) $violationType->violation_type_id)
                        ->orWhere('violation_type', $violationType->violation_type);
                })
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

                'student_number' => $validated['student_number'],

                'violation_type' => $violationType->violation_type_id,

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

            DB::commit();

            // Notify the student about the recorded violation (target-aware).
            try {
                app(\App\Services\StudentNotificationService::class)->create(
                    studentNumber: $validated['student_number'],
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

    DB::rollBack();

    return response()->json([
        'message' => $exception->getMessage(),
        'line' => $exception->getLine(),
        'file' => basename($exception->getFile())
    ], 500);
}
}

    public function history(Request $request)
{
    $violations = Violation::with([
        'student.studentInfo.program',
        'violationType.violationCategory',
        'recorder.adminInfo'
    ])
    ->notDenied()
    ->when($request->student, function ($query) use ($request) {
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

        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk((string) config('filesystems.evidence_disk', 'local'));

        if (! $evidence->disk_path || ! $disk->exists($evidence->disk_path)) {
            abort(404);
        }

        // Audit the access + record an evidence security event.
        app(EvidenceSecurityService::class)->auditEvidenceAccess(
            'EVIDENCE_VIEWED',
            $evidence,
            'admin',
            $adminId,
            $request,
        );

        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        return $disk->response(
            $evidence->disk_path,
            $evidence->original_name ?: basename($evidence->disk_path)
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
                'watermarked' => $evidence->watermarked,
            ],
            'events' => $events,
        ]);
    }

}