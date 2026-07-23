<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use App\Models\User;
use App\Models\Student;
use App\Models\Violation;
use App\Models\ViolationType;
use App\Models\ViolationCategory;
use App\Models\DisciplinarySanction;
use App\Models\Program;
use App\Models\Year;
use App\Models\Section;
use App\Models\StudentStatus;
use App\Models\SanctionRequest;


class ViolationController extends Controller
{
public function create()
{
    return redirect()->route('admin.violations.index');
}

public function index()
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
    'violations',
    'role'
])
->whereHas('role', function ($query) {
    $query->where('roles_name', 'Student');
})
->orderBy('last_name')
->paginate(10);

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
    ->latest('violation_date')
    ->paginate(15);

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
            'violationHistory',
             'categories',
        'violationTypes'
        )
    );
}

public function show($student_number)
{
    /*
    |--------------------------------------------------------------------------
    | Student Information
    |--------------------------------------------------------------------------
    */

    $student = User::with([
        'studentInfo.program',
        'studentInfo.year',
        'studentInfo.section',
        'studentInfo.studentStatus',
    ])
    ->where('student_number', $student_number)
    ->firstOrFail();

    /*
    |--------------------------------------------------------------------------
    | All Violations (Used for Summary)
    |--------------------------------------------------------------------------
    */

    $allViolations = Violation::with([
        'violationType.violationCategory',
        'recorder.adminInfo'
    ])
    ->where('student_number', $student_number)
    ->latest('violation_date')
    ->get();

    /*
    |--------------------------------------------------------------------------
    | Individual Violations Log (Paginated)
    |--------------------------------------------------------------------------
    */

    $individualViolations = Violation::with([
        'violationType.violationCategory',
        'recorder.adminInfo'
    ])
    ->where('student_number', $student_number)
    ->latest('violation_date')
    ->paginate(10);

    /*
    |--------------------------------------------------------------------------
    | Summary by Violation
    |--------------------------------------------------------------------------
    */

    $summary = $allViolations
        ->groupBy('violation_type')
        ->map(function ($records) {

            $latest = $records->first();

            $offenseLevel = $this->formatOffenseLevel(
                $records->count()
            );

            /*
            |--------------------------------------------------------------------------
            | Disciplinary Sanction
            |--------------------------------------------------------------------------
            */

            $disciplinarySanction = DisciplinarySanction::where(
                    'violation_type_id',
                    optional($latest->violationType)->violation_type_id
                )
                ->where(
                    'offense_level',
                    $offenseLevel
                )
                ->first();

            /*
            |--------------------------------------------------------------------------
            | Latest Sanction Request
            |--------------------------------------------------------------------------
            */

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

            return [

                'category' => optional(
                    optional($latest->violationType)->violationCategory
                )->category_name,

                'violation_type' => $latest->violation_type,

                'offense_level' => $offenseLevel,

                'status' => $sanctionRequest?->status ?? 'No Request',

                'remarks' => $records->count() > 1
                    ? '(Multiple instances - see log)'
                    : ($latest->description ?: '-'),

                'sanction' => $disciplinarySanction?->disciplinary_sanction
                    ?? '-',

                'total' => $records->count(),

            ];

        })
        ->values();

    /*
    |--------------------------------------------------------------------------
    | Return View
    |--------------------------------------------------------------------------
    */

    return view(
        'admin.violations.partials.student-history-content',
        compact(
            'student',
            'summary',
            'individualViolations'
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
        'studentInfo.studentStatus'
    ])
    ->where(function ($query) use ($keyword) {

        $query->where('student_number', 'like', "%{$keyword}%")
              ->orWhere('first_name', 'like', "%{$keyword}%")
              ->orWhere('last_name', 'like', "%{$keyword}%");

    })
    ->orderBy('last_name')
    ->limit(10)
    ->get();

    return response()->json(

        $students->map(function ($student) {

            return [

                'student_number' => $student->student_number,

                'first_name' => $student->first_name,

                'middle_name' => $student->middle_name,

                'last_name' => $student->last_name,

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

        })

    );
}

    /*
    |--------------------------------------------------------------------------
    | Load Violation Types
    |--------------------------------------------------------------------------
    */

  public function getViolationTypes(Request $request): JsonResponse
{
    $categoryId = $request->get('category_id');

    if (!$categoryId) {
        return response()->json([]);
    }

    $types = ViolationType::where(
            'violation_category_id',
            $categoryId
        )
        ->orderBy('violation_type')
        ->get([
            'violation_type_id',
            'violation_type',
            'violation_description',
            'resolution_number',
        ]);

    return response()->json($types);
}

    /*
    |--------------------------------------------------------------------------
    | CONTINUES IN PART 2
    |--------------------------------------------------------------------------
    */
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
    ->where(
        'violation_type',
        $violationType->violation_type
    )
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

            $count % 100 >= 11 &&
            $count % 100 <= 13 => 'th',

            $count % 10 === 1 => 'st',

            $count % 10 === 2 => 'nd',

            $count % 10 === 3 => 'rd',

            default => 'th',

        };

        return "{$count}{$suffix} Offense";
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

        // ... the rest of your code ...

            /*
            |--------------------------------------------------------------------------
            | Prevent Duplicate Records
            |--------------------------------------------------------------------------
            */

            $exists = Violation::where(
                    'student_number',
                    $validated['student_number']
                )
                ->where(
    'violation_type',
    $violationType->violation_type
)
                ->whereDate(
                    'violation_date',
                    date(
                        'Y-m-d',
                        strtotime($validated['violation_date'])
                    )
                )
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

            $violation = Violation::create([

                'student_number' => $validated['student_number'],

                'violation_type' => $violationType->violation_type,

                'violation_date' => $validated['violation_date'],

                'description' => trim(
                    $validated['description']
                ),

'recorder_id' => Auth::guard('admin')->id(),
            ]);

            DB::commit();

            return response()->json([

                'success' => true,

                'message' =>
                    'Violation recorded successfully.',

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
    ->when($request->student, function ($query) use ($request) {
        $query->whereHas('student', function ($q) use ($request) {
            $q->where('student_number', 'like', "%{$request->student}%")
              ->orWhere('first_name', 'like', "%{$request->student}%")
              ->orWhere('last_name', 'like', "%{$request->student}%");
        });
    })
    ->latest('violation_date')
    ->paginate(15);

    return view(
        'admin.violations.history',
        compact('violations')
    );
}



}