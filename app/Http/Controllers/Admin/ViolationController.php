<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Violation;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Course;
use App\Models\Year;
use App\Models\ViolationType;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;
use App\Models\DisciplinarySanction;
use App\Models\ViolationCategory;

class ViolationController extends Controller
{
public function index(Request $request)
{
    $students = User::with([
        'course',
        'year',
        'section',
        'violations.violationType.category'
    ])
    ->whereHas('violations');
    $allStudents = User::whereHas('role', function ($q) {

    $q->where('roles_name', 'Student');

})
->orderBy('last_name')
->get();

    /*
    |--------------------------------------------------------------------------
    | Search
    |--------------------------------------------------------------------------
    */

    if ($request->filled('search')) {

        $search = $request->search;

        $students->where(function ($query) use ($search) {

            $query->where('student_number', 'like', "%{$search}%")
                  ->orWhere('first_name', 'like', "%{$search}%")
                  ->orWhere('middle_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%");

        });
    }

    /*
    |--------------------------------------------------------------------------
    | Course Filter
    |--------------------------------------------------------------------------
    */

    if ($request->filled('course')) {

        $students->where('course_id', $request->course);

    }

    /*
    |--------------------------------------------------------------------------
    | Year Filter
    |--------------------------------------------------------------------------
    */

    if ($request->filled('year')) {

        $students->where('year_id', $request->year);

    }

    /*
    |--------------------------------------------------------------------------
    | Violation Type Filter
    |--------------------------------------------------------------------------
    */

    if ($request->filled('violation_type')) {

        $students->whereHas('violations', function ($query) use ($request) {

            $query->where(
                'violation_type',
                $request->violation_type
            );

        });

    }

    $students = $students
        ->orderBy('last_name')
        ->paginate(15);

    $courses = Course::orderBy('course_name')->get();

    $years = Year::orderBy('year')->get();

    $violationTypes = ViolationType::orderBy('violation_type')->get();
    $categories = ViolationCategory::orderBy('category_name')->get();

    return view(
    'admin.violations.index',
    compact(
    'students',
    'courses',
    'years',
    'violationTypes',
    'allStudents',
    'categories'
)
);
    
}
public function show($student_number)
{
    $student = User::with([
        'course',
        'year',
        'section',
        'violations.violationType.category',
        'violations.recorder'
    ])
    ->where('student_number', $student_number)
    ->firstOrFail();

    $violations = $student->violations
        ->sortByDesc('violation_date')
        ->values();

    $summary = $violations
        ->groupBy('violation_type')
        ->map(function ($group) {

            $first = $group->first();

            $count = $group->count();

            if ($count == 1) {
                $offenseLevel = '1st Offense';
            } elseif ($count == 2) {
                $offenseLevel = '2nd Offense';
            } elseif ($count == 3) {
                $offenseLevel = '3rd Offense';
            } else {
                $offenseLevel = $count . 'th Offense';
            }

            $sanction = DisciplinarySanction::where(
                'violation_type_id',
                $first->violationType->violation_type_id
            )
            ->where(
                'offense_level',
                $offenseLevel
            )
            ->first();

            return [

                'category' => $first->violationType->category,

                'violation_type' => $first->violationType,

                'offense_level' => $offenseLevel,

                'count' => $count,

                'sanction' => $sanction?->disciplinary_sanction,

                'status' => str_contains(
                    strtolower($sanction?->disciplinary_sanction ?? ''),
                    'warning'
                )
                    ? 'Warning'
                    : 'Sanction'

            ];

        });

    return view(
        'admin.violations.show',
        compact(
            'student',
            'violations',
            'summary'
        )
    );
}
public function create()
{
    $students = User::whereHas('role', function ($q) {

        $q->where('roles_name', 'Student');

    })
    ->orderBy('last_name')
    ->get();

    $violationTypes = ViolationType::with('category')
        ->orderBy('violation_type')
        ->get();

    return view(
        'admin.violations.create',
        compact(
            'students',
            'violationTypes'
        )
    );
}


public function store(Request $request)
{
    $request->validate([
        'student_number' => 'required|exists:users_tbl,student_number',
        'violation_type' => 'required|exists:violation_type_tbl,violation_type',
        'description' => 'nullable|string|max:1000',
    ]);

    /*
    |--------------------------------------------------------------------------
    | Save Violation
    |--------------------------------------------------------------------------
    */

    $violation = Violation::create([
        'student_number' => $request->student_number,
        'violation_type' => $request->violation_type,
        'violation_date' => now(),
        'description' => $request->description,
        'recorder_id' => Auth::id(),
    ]);

    /*
    |--------------------------------------------------------------------------
    | Compute Offense Level
    |--------------------------------------------------------------------------
    */

    $offenseCount = Violation::where(
        'student_number',
        $request->student_number
    )
    ->where(
        'violation_type',
        $request->violation_type
    )
    ->count();

    $offenseLevel = match ($offenseCount) {
        1 => '1st Offense',
        2 => '2nd Offense',
        3 => '3rd Offense',
        default => $offenseCount . 'th Offense',
    };

    /*
    |--------------------------------------------------------------------------
    | Lookup Sanction
    |--------------------------------------------------------------------------
    */

    $type = ViolationType::where(
        'violation_type',
        $request->violation_type
    )->first();

    $sanction = null;

    if ($type) {

        $sanction = DisciplinarySanction::where(
            'violation_type_id',
            $type->violation_type_id
        )
        ->where(
            'offense_level',
            $offenseLevel
        )
        ->first();

    }

    /*
    |--------------------------------------------------------------------------
    | Create Notification
    |--------------------------------------------------------------------------
    */

    \App\Models\Notification::create([

        'student_number' => $request->student_number,

        'message' => $sanction
            ? "A new violation has been recorded.\n\nViolation: {$request->violation_type}\n{$offenseLevel}\nSanction: {$sanction->disciplinary_sanction}"
            : "A new violation has been recorded.\n\nViolation: {$request->violation_type}\n{$offenseLevel}",

        'notification_type' => 'Violation',

        'recipient_type' => 'student',

        'is_read' => false,

    ]);

    return redirect()
        ->route('admin.violations')
        ->with('success', 'Violation recorded successfully.');
}
public function getOffenseLevel(Request $request): JsonResponse
{
    $count = Violation::where(
            'student_number',
            $request->student_number
        )
        ->where(
            'violation_type',
            $request->violation_type
        )
        ->count();

    return response()->json([

        'offense' => $count + 1

    ]);
}
public function getViolationTypes($categoryId)
{
    return ViolationType::where(
        'violation_category_id',
        $categoryId
    )
    ->orderBy('violation_type')
    ->get();
}

public function previewViolation(Request $request): JsonResponse
{
    $request->validate([
        'student_number' => 'required',
        'violation_type' => 'required',
    ]);

    $count = Violation::where(
        'student_number',
        $request->student_number
    )
    ->where(
        'violation_type',
        $request->violation_type
    )
    ->count();

    $offenseNumber = $count + 1;

    $offenseLevel = match ($offenseNumber) {
        1 => '1st Offense',
        2 => '2nd Offense',
        3 => '3rd Offense',
        default => $offenseNumber . 'th Offense',
    };

    $type = ViolationType::where(
        'violation_type',
        $request->violation_type
    )->first();

    $sanction = null;

    if ($type) {

        $sanction = DisciplinarySanction::where(
            'violation_type_id',
            $type->violation_type_id
        )
        ->where(
            'offense_level',
            $offenseLevel
        )
        ->first();

    }

    return response()->json([

        'offense_level' => $offenseLevel,

        'sanction' => $sanction?->disciplinary_sanction,

    ]);
}
public function searchStudent(Request $request): JsonResponse
{
    $search = $request->get('search');

    $students = User::whereHas('role', function ($query) {
            $query->where('roles_name', 'Student');
        })
        ->where(function ($query) use ($search) {

            $query->where('student_number', 'like', "%{$search}%")
                  ->orWhere('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%");

        })
        ->orderBy('last_name')
        ->limit(10)
        ->get([
            'student_number',
            'first_name',
            'last_name',
            'course_id',
            'year_id'
        ]);

    return response()->json($students);
}
}
