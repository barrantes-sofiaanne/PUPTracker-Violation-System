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
->toArray();

$pendingSanctions = StudentSanctionRecord::where(
    'student_number',
    $user->student_number
)
->where('status', 'Pending')
->pluck('violation_id')
->toArray();

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

    // Safely look up sanction without throwing errors on null relationships
    $sanction = $first
        ->violationType
        ?->disciplinarySanction
        ?->firstWhere('offense_level', $offenseLevel);

    $disciplinarySanction = $sanction->disciplinary_sanction ?? 'N/A';

    $status = str_contains(strtolower($disciplinarySanction), 'warning')
        ? 'Warning'
        : 'Sanction';

    if (in_array($first->violation_type, $activeRequests)) {
        $workflowStatus = 'Requested';
    } elseif (in_array($first->violation_id, $pendingSanctions)) {
        $workflowStatus = 'Pending';
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