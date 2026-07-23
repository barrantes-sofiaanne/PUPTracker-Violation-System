<?php

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use App\Models\DisciplinarySanction;
use App\Models\ViolationCategory;
use App\Models\ViolationType;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Violation;
use Illuminate\Support\Collection;

class StudentViolationController extends Controller
{
    /**
     * Display a listing of students with violations
     */
    public function index()
    {
        $students = User::whereHas('violations')
            ->with(['violations' => function ($query) {
                $query->latest('violation_date')->take(5);
            }])
            ->withCount('violations')
            ->orderByDesc('violations_count')
            ->paginate(15);

        $categories = ViolationCategory::orderBy('category_name')->get();

        return view('security.violations.students', compact('students', 'categories'));
    }

    /**
     * Display violations for a specific student
     */
    public function show(string $student_number)
    {
        $student = User::where('student_number', $student_number)
            ->with(['violations' => function ($query) {
                $query->latest('violation_date');
            }, 'violations.violationType.violationCategory'])
            ->firstOrFail();

        $violations = $student->violations;
        $violationStats = $this->getViolationStats($student_number);

        return view('security.violations.show', compact('student', 'violations', 'violationStats'));
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
            ->where('violation_type', $violationType->violation_type)
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

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_number' => ['required', 'exists:users_tbl,student_number'],
            'violation_type_id' => ['required', 'exists:violation_type_tbl,violation_type_id'],
            'violation_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:1000'],
        ]);

        $violationType = ViolationType::findOrFail($validated['violation_type_id']);

        DB::beginTransaction();

        try {
            $exists = Violation::where('student_number', $validated['student_number'])
                ->where('violation_type', $violationType->violation_type)
                ->whereDate('violation_date', Carbon::parse($validated['violation_date'])->toDateString())
                ->exists();

            if ($exists) {
                DB::rollBack();

                return response()->json([
                    'message' => 'A violation of this type has already been recorded for this student today.',
                ], 422);
            }

            $violation = Violation::create([
                'student_number' => $validated['student_number'],
                'violation_type' => $violationType->violation_type,
                'violation_date' => $validated['violation_date'],
                'description' => trim($validated['description']),
                'recorder_id' => Auth::guard('security')->id(),
            ]);

            DB::commit();

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
                'file' => basename($exception->getFile()),
            ], 500);
        }
    }

    public function report(Request $request)
    {
        $reportStudents = $this->buildReportStudents();

        return view('security.violations.report', [
            'reportStudents' => $reportStudents,
            'generatedAt' => now(),
            'filtersApplied' => 'None',
        ]);
    }

    /**
     * Get violation statistics for a student
     */
    private function getViolationStats(string $student_number)
    {
        $violations = Violation::where('student_number', $student_number)
            ->with(['violationType.violationCategory']);

        $byCategory = (clone $violations)
            ->get()
            ->groupBy(fn ($violation) => optional($violation->violationType?->violationCategory)->category_name ?? 'Unknown')
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

    private function buildReportStudents(): Collection
    {
        $violations = Violation::with([
                'student.studentInfo.program',
                'student.studentInfo.year',
                'student.studentInfo.section',
                'violationType.violationCategory',
                'violationType.disciplinarySanctions',
            ])
            ->latest('violation_date')
            ->get();

        return $violations
            ->groupBy('student_number')
            ->map(function ($studentViolations) {
                $firstViolation = $studentViolations->first();
                $student = $firstViolation?->student;

                $summaries = $studentViolations
                    ->groupBy('violation_type')
                    ->map(function ($records) {
                        $first = $records->first();
                        $count = $records->count();
                        $offenseLevel = $this->formatOffenseLevel($count);

                        $disciplinarySanction = DisciplinarySanction::where(
                                'violation_type_id',
                                optional($first->violationType)->violation_type_id
                            )
                            ->where('offense_level', $offenseLevel)
                            ->first();

                        $sanctionText = $disciplinarySanction?->disciplinary_sanction ?? 'N/A';

                        return (object) [
                            'category' => $first->violationType?->violationCategory?->category_name ?? 'N/A',
                            'type' => $first->violationType?->violation_type ?? 'Unknown',
                            'offense_level' => $offenseLevel,
                            'remarks' => $count > 1 ? '(Multiple instances - see log)' : ($first->description ?: 'No remarks'),
                            'date_recorded' => $first->violation_date,
                            'status' => str_contains(strtolower($sanctionText), 'warning') ? 'Warning' : 'Sanction',
                        ];
                    })
                    ->values();

                return (object) [
                    'student' => $student,
                    'records' => $summaries,
                ];
            })
            ->values()
            ->sortBy(function ($entry) {
                return trim(($entry->student?->last_name ?? '') . ', ' . ($entry->student?->first_name ?? ''));
            })
            ->values();
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
}
