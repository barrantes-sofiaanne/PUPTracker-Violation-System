<?php

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Violation;
use Illuminate\Support\Facades\DB;

class StudentViolationController extends Controller
{
    /**
     * Display a listing of students with violations
     */
    public function index()
    {
        $students = User::whereHas('violations')
            ->with(['violations' => function($query) {
                $query->latest('violation_date')->take(5);
            }])
            ->withCount('violations')
            ->orderByDesc('violations_count')
            ->paginate(15);

        return view('security.violations.students', compact('students'));
    }

    /**
     * Display violations for a specific student
     */
    public function show($student_number)
    {
        $student = User::where('student_number', $student_number)
            ->with(['violations' => function($query) {
                $query->latest('violation_date');
            }, 'violations.violationType.category'])
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
        $query = $request->get('q');
        
        if (strlen($query) < 2) {
            return response()->json([]);
        }

        $students = User::where('student_number', 'like', "%{$query}%")
            ->orWhere('first_name', 'like', "%{$query}%")
            ->orWhere('last_name', 'like', "%{$query}%")
            ->with('violations', 'course')
            ->withCount('violations')
            ->limit(10)
            ->get();

        return response()->json($students->map(function($student) {
            return [
                'id' => $student->student_number,
                'text' => "{$student->student_number} - {$student->last_name}, {$student->first_name}",
                'violations_count' => $student->violations_count,
                'course' => optional($student->course)->course_name,
            ];
        }));
    }

    /**
     * Get violation statistics for a student
     */
    private function getViolationStats($student_number)
    {
        $violations = Violation::where('student_number', $student_number);

        return [
            'total' => $violations->count(),
            'major' => (clone $violations)->where('offense_level', 'Major')->count(),
            'minor' => (clone $violations)->where('offense_level', 'Minor')->count(),
            'by_category' => (clone $violations)
                ->select('violation_category_id', DB::raw('COUNT(*) as total'))
                ->groupBy('violation_category_id')
                ->with('category')
                ->get()
        ];
    }
}
