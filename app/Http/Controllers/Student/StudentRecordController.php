<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\Violation;
use App\Models\User;
use App\Models\DisciplinarySanction;
use App\Models\StudentSanctionRecord;
use App\Models\SanctionRequest;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\AdminNotification;
class StudentRecordController extends Controller
{
    public function index()
    {
        /** @var User $user */
        $user = Auth::user();

       $user->load([
           'studentInfo.program',
           'studentInfo.year',
           'studentInfo.section',
       ]);

       $violations = Violation::with([
    'violationType.violationCategory',
    'violationType.disciplinarySanctions',
])
           ->where('student_number', $user->student_number)
           ->orderByDesc('violation_date')
           ->paginate(10);
/*
|--------------------------------------------------------------------------
| Summary by Violation
|--------------------------------------------------------------------------
*/

$violationSummary = [];

$activeRequests = SanctionRequest::where(
    'student_number',
    $user->student_number
)
->where('is_active', 1)
->pluck('violation_type_id')
->map(fn ($value) => (string) $value)
->toArray();

$approvedRequests = SanctionRequest::where(
    'student_number',
    $user->student_number
)
->where('is_active', 0)
->where('status', 'Approved')
->pluck('violation_type_id')
->map(fn ($value) => (string) $value)
->toArray();

$pendingSanctionTypeKeys = StudentSanctionRecord::with('violation')
    ->where('student_number', $user->student_number)
    ->where('status', 'Pending')
    ->whereHas('violation.violationType')
    ->get()
    ->flatMap(function ($record) {
        $raw = (string) ($record->violation?->violation_type ?? '');

        return array_filter([
            $raw,
            strtolower($raw),
        ]);
    })
    ->unique()
    ->values()
    ->toArray();

$approvedSanctionTypeKeys = StudentSanctionRecord::with('violation')
    ->where('student_number', $user->student_number)
    ->whereIn('status', ['Pending', 'Completed'])
    ->whereHas('violation.violationType')
    ->get()
    ->flatMap(function ($record) {
        $raw = (string) ($record->violation?->violation_type ?? '');

        return array_filter([
            $raw,
            strtolower($raw),
        ]);
    })
    ->unique()
    ->values()
    ->toArray();

$matchesViolationType = function ($violationRecord, array $pool): bool {
    $rawType = (string) ($violationRecord->violation_type ?? '');
    $relationTypeId = (string) ($violationRecord->violationType?->violation_type_id ?? '');
    $relationTypeName = (string) ($violationRecord->violationType?->violation_type ?? '');

    $candidates = array_filter([
        $rawType,
        strtolower($rawType),
        $relationTypeId,
        $relationTypeName,
        strtolower($relationTypeName),
    ]);

    foreach ($candidates as $candidate) {
        if (in_array($candidate, $pool, true)) {
            return true;
        }
    }

    return false;
};

$groupedViolations = Violation::with([
        'violationType.violationCategory',
    'violationType.disciplinarySanctions'
])
    ->where('student_number', $user->student_number)
    ->get()
    ->groupBy('violation_type');

foreach ($groupedViolations as $records) {

    $first = $records->first();
    $count = $records->count();

    $offenseLevel = match ($count) {
        1 => '1st Offense',
        2 => '2nd Offense',
        3 => '3rd Offense',
        default => $count . 'th Offense'
    };

    // Resolve sanction for this row using offense level, then fallback to any configured sanction.
    $sanctions = $first->violationType?->disciplinarySanctions;

    $sanction = $sanctions?->firstWhere('offense_level', $offenseLevel)
        ?? $sanctions?->first();

    $disciplinarySanction = $sanction->disciplinary_sanction ?? 'N/A';

    $status = str_contains(strtolower($disciplinarySanction), 'warning')
        ? 'Warning'
        : 'Sanction';

    if ($matchesViolationType($first, $activeRequests)) {
        $workflowStatus = 'Requested';
    } elseif ($matchesViolationType($first, $pendingSanctionTypeKeys)) {
        $workflowStatus = 'Pending';
    } elseif (
        $matchesViolationType($first, $approvedRequests)
        || $matchesViolationType($first, $approvedSanctionTypeKeys)
    ) {
        $workflowStatus = 'Approved';
    } else {
        $workflowStatus = 'Actionable';
    }

    $violationSummary[] = [
        // Using ?-> prevents the "reading property on null" error
        'category' => $first->violationType?->violationCategory?->category_name ?? 'N/A',

        'type' => $first->violationType?->violation_type ?? 'Unknown',

        'violation_type_id' => $first->violation_type,

        'offense_level' => $offenseLevel,

        'remarks' => $count > 1
            ? '(Multiple instances - see log)'
            : ($first->description ?: 'No remarks'),

        'disciplinary_sanction' => $disciplinarySanction,

        'violation_status' => $status,

        'workflow_status' => $workflowStatus
    ];
}

$sanctionRecords = StudentSanctionRecord::with([
    'violation.violationType',
    'assignedSanction'
])
->where(
    'student_number',
    $user->student_number
)
->whereHas('violation.violationType')
->orderByDesc('date_assigned')
->get();

        return view(
            'student.record',
compact(
    'user',
    'violations',
    'violationSummary',
    'sanctionRecords'
)
        );
    }

public function requestSanction(Request $request)
{
    $request->validate([
        'violation_type_id' => 'required|integer'
    ]);

    $user = Auth::user();

    $existing = SanctionRequest::where(
        'student_number',
        $user->student_number
    )
    ->where(
        'violation_type_id',
        $request->violation_type_id
    )
    ->where(
        'is_active',
        1
    )
    ->first();

    if ($existing) {

        return response()->json([

            'success' => false,

            'message' => 'You already have an active request.'

        ]);

    }

    DB::beginTransaction();

    try {

        SanctionRequest::create([

            'student_number' => $user->student_number,

            'violation_type_id' => $request->violation_type_id

        ]);

        AdminNotification::create([

            'message' => $user->first_name .
                ' ' .
                $user->last_name .
                ' requested a disciplinary sanction.',

            'link' => route('student.record')

        ]);

        DB::commit();

        return response()->json([

            'success' => true,

            'message' => 'Request submitted successfully.'

        ]);

    } catch (\Exception $e) {

        DB::rollBack();

        return response()->json([

            'success' => false,

            'message' => $e->getMessage()

        ], 500);

    }
}
}