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
    ->get()
    ->flatMap(function ($record) {
        $raw = (string) ($record->violation?->violation_type ?? '');
        $resolvedTypeId = (string) ($record->violation?->resolvedViolationType()?->violation_type_id ?? '');
        $resolvedTypeName = (string) ($record->violation?->resolvedViolationType()?->violation_type ?? '');

        return array_filter([
            $raw,
            strtolower($raw),
            $resolvedTypeId,
            $resolvedTypeName,
            strtolower($resolvedTypeName),
        ]);
    })
    ->unique()
    ->values()
    ->toArray();

$approvedSanctionTypeKeys = StudentSanctionRecord::with('violation')
    ->where('student_number', $user->student_number)
    ->whereIn('status', ['Pending', 'Completed'])
    ->get()
    ->flatMap(function ($record) {
        $raw = (string) ($record->violation?->violation_type ?? '');
        $resolvedTypeId = (string) ($record->violation?->resolvedViolationType()?->violation_type_id ?? '');
        $resolvedTypeName = (string) ($record->violation?->resolvedViolationType()?->violation_type ?? '');

        return array_filter([
            $raw,
            strtolower($raw),
            $resolvedTypeId,
            $resolvedTypeName,
            strtolower($resolvedTypeName),
        ]);
    })
    ->unique()
    ->values()
    ->toArray();

$matchesViolationType = function ($violationRecord, array $pool): bool {
    $rawType = (string) ($violationRecord->violation_type ?? '');
    $relationTypeId = (string) ($violationRecord->violationType?->violation_type_id ?? '');
    $relationTypeName = (string) ($violationRecord->violationType?->violation_type ?? '');
    $resolvedType = $violationRecord->resolvedViolationType();
    $resolvedTypeId = (string) ($resolvedType?->violation_type_id ?? '');
    $resolvedTypeName = (string) ($resolvedType?->violation_type ?? '');

    $candidates = array_filter([
        $rawType,
        strtolower($rawType),
        $relationTypeId,
        $relationTypeName,
        strtolower($relationTypeName),
        $resolvedTypeId,
        $resolvedTypeName,
        strtolower($resolvedTypeName),
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
    ->groupBy(function ($violation) {
        $resolvedType = $violation->resolvedViolationType();

        if (!empty($resolvedType?->violation_type_id)) {
            return 'id:' . $resolvedType->violation_type_id;
        }

        return 'raw:' . strtolower(trim((string) $violation->violation_type));
    });

foreach ($groupedViolations as $records) {

    $first = $records->first();
    $count = $records->count();

    $offenseLevel = match ($count) {
        1 => '1st Offense',
        2 => '2nd Offense',
        3 => '3rd Offense',
        default => $count . 'th Offense'
    };

    $resolvedType = $first->resolvedViolationType();

    $sanction = null;
    if (!empty($resolvedType?->violation_type_id)) {
        $sanction = DisciplinarySanction::where('violation_type_id', $resolvedType->violation_type_id)
            ->where('offense_level', $offenseLevel)
            ->first()
            ?? DisciplinarySanction::where('violation_type_id', $resolvedType->violation_type_id)
                ->orderBy('disciplinary_sanction_id')
                ->first();
    }

    $disciplinarySanction = $sanction->disciplinary_sanction ?? 'N/A';

    $status = 'Unknown';
    if (str_contains(strtolower($disciplinarySanction), 'warning')) {
        $status = 'Warning';
    } elseif ($disciplinarySanction !== 'N/A') {
        $status = 'Sanction';
    }

    if (
        $matchesViolationType($first, $pendingSanctionTypeKeys)
        || $matchesViolationType($first, $approvedRequests)
        || $matchesViolationType($first, $approvedSanctionTypeKeys)
    ) {
        // Once a violation enters sanction workflow, it should be shown only in Sanction Record.
        continue;
    }

    $workflowStatus = $matchesViolationType($first, $activeRequests)
        ? 'Requested'
        : 'Actionable';

    $violationSummary[] = [
        // Using ?-> prevents the "reading property on null" error
        'category' => $first->violation_category_display,

        'type' => $first->violation_type_display,

        'violation_type_id' => $resolvedType?->violation_type_id,

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

    $violationType = \App\Models\ViolationType::find($request->violation_type_id);

    if (!$violationType) {
        return response()->json([
            'success' => false,
            'message' => 'Violation type was not found.'
        ], 422);
    }

    $offenseCount = Violation::where('student_number', $user->student_number)
        ->where(function ($query) use ($violationType) {
            $query->where('violation_type', (string) $violationType->violation_type_id)
                ->orWhere('violation_type', $violationType->violation_type);
        })
        ->count();

    if ($offenseCount < 1) {
        return response()->json([
            'success' => false,
            'message' => 'No matching violation record found for this request.'
        ], 422);
    }

    $offenseLevel = match ($offenseCount) {
        1 => '1st Offense',
        2 => '2nd Offense',
        3 => '3rd Offense',
        default => $offenseCount . 'th Offense',
    };

    $assignedSanction = DisciplinarySanction::where('violation_type_id', $violationType->violation_type_id)
        ->where('offense_level', $offenseLevel)
        ->first()
        ?? DisciplinarySanction::where('violation_type_id', $violationType->violation_type_id)
            ->orderBy('disciplinary_sanction_id')
            ->first();

    if (!$assignedSanction) {
        return response()->json([
            'success' => false,
            'message' => 'No disciplinary sanction is configured for this violation yet.'
        ], 422);
    }

    $sanctionText = strtolower((string) ($assignedSanction?->disciplinary_sanction ?? ''));
    if ($sanctionText !== '' && str_contains($sanctionText, 'warning')) {
        return response()->json([
            'success' => false,
            'message' => 'Warning-level violations remain in the violation log and cannot be moved to sanction records.'
        ], 422);
    }

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

        $defaultAdminId = (int) (DB::table('admins')->min('id') ?? 0);

        if ($defaultAdminId <= 0) {
            throw new \RuntimeException('Cannot queue sanction request because no admin account is available.');
        }

        $sanctionRequest = SanctionRequest::create([

            'student_number' => $user->student_number,

            'violation_type_id' => $request->violation_type_id,

            'request_date' => now(),

            'is_active' => true,

            'status' => 'Pending',

            'approved_by_admin_id' => null,

            'approved_at' => null,

        ]);

        $latestViolation = Violation::where('student_number', $user->student_number)
            ->where(function ($query) use ($violationType) {
                $query->where('violation_type', (string) $violationType->violation_type_id)
                    ->orWhere('violation_type', $violationType->violation_type);
            })
            ->latest('violation_date')
            ->first();

        if (!$latestViolation) {
            throw new \RuntimeException('Unable to locate the latest violation for this sanction request.');
        }

        $existingPendingRecord = StudentSanctionRecord::where('student_number', $user->student_number)
            ->where('violation_id', $latestViolation->violation_id)
            ->where('status', 'Pending')
            ->first();

        if (!$existingPendingRecord) {
            StudentSanctionRecord::create([
                'student_number' => $user->student_number,
                'violation_id' => $latestViolation->violation_id,
                'assigned_sanction_id' => $assignedSanction->disciplinary_sanction_id,
                'assigned_by_admin_id' => $defaultAdminId,
                'status' => 'Pending',
                'date_assigned' => now(),
            ]);
        }

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

            'message' => 'Request submitted successfully. It is now in your Sanction Record as Pending.',

            'request_id' => $sanctionRequest->request_id,

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