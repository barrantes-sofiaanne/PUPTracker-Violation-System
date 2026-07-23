<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

use App\Models\User;
use App\Models\Course;
use App\Models\Year;
use App\Models\Violation;
use App\Models\ViolationType;
use App\Models\ViolationCategory;
use App\Models\DisciplinarySanction;
use App\Models\Notification;

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
    ->whereHas('role', function ($q) {
        $q->where('roles_name', 'Student');
    })
    ->whereHas('violations');

    if ($request->filled('search')) {

        $search = $request->search;

        $students->where(function ($q) use ($search) {

            $q->where('student_number','like',"%{$search}%")
              ->orWhere('first_name','like',"%{$search}%")
              ->orWhere('middle_name','like',"%{$search}%")
              ->orWhere('last_name','like',"%{$search}%");

        });

    }

    if ($request->filled('course')) {

        $students->where('course_id',$request->course);

    }

    if ($request->filled('year')) {

        $students->where('year_id',$request->year);

    }

    if ($request->filled('category')) {

        $students->whereHas('violations.violationType', function($q) use ($request){

            $q->where(
                'violation_category_id',
                $request->category
            );

        });

    }

    if ($request->filled('violation_type')) {

        $students->whereHas('violations', function($q) use ($request){

            $q->where(
                'violation_type',
                $request->violation_type
            );

        });

    }

    $students = $students
        ->orderBy('last_name')
        ->paginate(15);

    return view(
        'admin.violations.index',
        [
            'students'=>$students,
            'courses'=>Course::orderBy('course_name')->get(),
            'years'=>Year::orderBy('year')->get(),
            'categories'=>ViolationCategory::orderBy('category_name')->get(),
            'violationTypes'=>ViolationType::orderBy('violation_type')->get()
        ]
    );
}
public function show($student_number)
{
    $student = User::with([
        'course',
        'year',
        'section',
        'violations' => function ($query) {
            $query->orderByDesc('violation_date');
        },
        'violations.violationType.category',
        'violations.recorder'
    ])
    ->where('student_number', $student_number)
    ->firstOrFail();

    $violations = $student->violations;

    /*
    |--------------------------------------------------------------------------
    | Build Violation Summary
    |--------------------------------------------------------------------------
    */

    $summary = $violations
        ->groupBy('violation_type')
        ->map(function ($group) {

            $first = $group->first();

            if (!$first->violationType) {
                return null;
            }

            $count = $group->count();

            $offenseLevel = $this->getOffenseLevel($count);

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

                'sanction' => optional($sanction)->disciplinary_sanction,

                'status' => $sanction &&
                    str_contains(
                        strtolower($sanction->disciplinary_sanction),
                        'warning'
                    )
                        ? 'Warning'
                        : 'Sanction'

            ];

        })
        ->filter()
        ->values();

    /*
    |--------------------------------------------------------------------------
    | Statistics
    |--------------------------------------------------------------------------
    */

    $statistics = [

        'total' => $violations->count(),

        'minor' => $violations
            ->filter(function ($v) {

                return optional($v->violationType->category)
                    ->category_name == 'Minor';

            })
            ->count(),

        'major' => $violations
            ->filter(function ($v) {

                return optional($v->violationType->category)
                    ->category_name == 'Major';

            })
            ->count(),

        'latest' => optional(
            $violations->first()
        ),

    ];

    return view(
        'admin.violations.show',
        compact(
            'student',
            'violations',
            'summary',
            'statistics'
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
        ->orderBy('violation_type_id')
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
    $validated = $request->validate([
        'student_number' => 'required|exists:user_tbl,student_number',
        'violation_type' => 'required|exists:violation_type_tbl,violation_type',
        'violation_date' => 'required|date',
        'description' => 'nullable|string|max:1000',
        'evidence' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120', // Max 5MB file
    ]);

    $evidencePath = null;
    
    if ($request->hasFile('evidence')) {
        // Upload the file to the 'evidence_files' folder in R2 bucket
        $evidencePath = $request->file('evidence')->store('evidence_files', 'r2');
    }

    $violation = Violation::create([
        'student_number' => $validated['student_number'],
        'violation_type' => $validated['violation_type'],
        'violation_date' => $validated['violation_date'],
        'description' => $validated['description'] ?? null,
        'recorder_id' => Auth::id(),
        'evidence_path' => $evidencePath,
    ]);

    // Load relationship
    $violation->load('violationType.category');

    /*
    |--------------------------------------------------------------------------
    | Count Previous Offenses
    |--------------------------------------------------------------------------
    */

    $offenseCount = Violation::where(
        'student_number',
        $validated['student_number']
    )
    ->where(
        'violation_type',
        $validated['violation_type']
    )
    ->count();

    $offenseLevel = $this->getOffenseLevel($offenseCount);

    /*
    |--------------------------------------------------------------------------
    | Get Corresponding Sanction
    |--------------------------------------------------------------------------
    */

    $sanction = null;

    if ($violation->violationType) {

        $sanction = DisciplinarySanction::where(
            'violation_type_id',
            $violation->violationType->violation_type_id
        )
        ->where(
            'offense_level',
            $offenseLevel
        )
        ->first();

    }

    /*
    |--------------------------------------------------------------------------
    | Notify Student
    |--------------------------------------------------------------------------
    */

    Notification::create([

        'student_number' => $validated['student_number'],

        'title' => 'New Violation Recorded',

        'message' => $this->buildNotificationMessage(
            $violation,
            $offenseLevel,
            $sanction
        ),

        'is_read' => 0,

    ]);

    return redirect()
        ->route(
            'admin.violations.show',
            $validated['student_number']
        )
        ->with(
            'success',
            'Violation recorded successfully.'
        );
}

private function buildNotificationMessage(
    Violation $violation,
    string $offenseLevel,
    ?DisciplinarySanction $sanction
): string {

    $message = "A new violation has been recorded.\n\n";

    $message .= "Violation: ";

    $message .= $violation->violation_type;

    $message .= "\n";

    $message .= "Offense Level: ";

    $message .= $offenseLevel;

    if ($sanction) {

        $message .= "\n";

        $message .= "Sanction: ";

        $message .= $sanction->disciplinary_sanction;

    }

    return $message;

}
private function getOffenseLevel(int $count): string
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

        $count % 100 >= 11 &&
        $count % 100 <= 13 => 'th',

        $count % 10 === 1 => 'st',

        $count % 10 === 2 => 'nd',

        $count % 10 === 3 => 'rd',

        default => 'th',

    };

    return "{$count}{$suffix} Offense";
}
public function getViolationTypes($categoryId): JsonResponse
{
    $violationTypes = ViolationType::where(
            'violation_category_id',
            $categoryId
        )
        ->orderBy('violation_type_id')
        ->get([
            'violation_type_id',
            'violation_type'
        ]);

    return response()->json($violationTypes);
}

public function previewViolation(Request $request): JsonResponse
{
    $request->validate([
        'student_number' => 'required',
        'violation_type' => 'required'
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

    $offenseLevel = $this->getOffenseLevel($count + 1);

    $type = ViolationType::where(
        'violation_type',
        $request->violation_type
    )
    ->with('category')
    ->first();

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

        'violation' => $type,

        'sanction' => $sanction

    ]);
}

public function searchStudent(Request $request): JsonResponse
{
    $request->validate([
        'search' => 'required|string'
    ]);

    $search = trim($request->search);

    $students = User::with([
        'course',
        'year',
        'section'
    ])
    ->whereHas('role', function ($query) {
        $query->where('roles_name', 'Student');
    })
    ->where(function ($query) use ($search) {

        $query->where('student_number', 'like', "%{$search}%")
            ->orWhere('first_name', 'like', "%{$search}%")
            ->orWhere('middle_name', 'like', "%{$search}%")
            ->orWhere('last_name', 'like', "%{$search}%");

    })
    ->limit(15)
    ->get();

    return response()->json($students);
}

public function studentHistory($studentNumber): JsonResponse
{
    $history = Violation::with([
        'violationType.category',
        'recorder'
    ])
    ->where(
        'student_number',
        $studentNumber
    )
    ->orderByDesc('violation_date')
    ->get();

    return response()->json($history);
}
}
