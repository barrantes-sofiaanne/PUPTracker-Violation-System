<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Violation;
use App\Models\Course;
use App\Models\Year;
use App\Models\ViolationCategory;
use App\Models\ViolationType;
use Carbon\Carbon;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $statistics = $this->getStatistics($request);

        $reports = $this->buildQuery($request)
            ->latest('violation_date')
            ->paginate(15);

        $courses = Course::orderBy('course_name')->get();
        $years = Year::orderBy('year')->get();
        $categories = ViolationCategory::orderBy('category_name')->get();
        $violationTypes = ViolationType::orderBy('violation_type')->get();

        return view(
            'admin.reports.index',
            compact(
                'reports',
                'statistics',
                'courses',
                'years',
                'categories',
                'violationTypes'
            )
        );
    }

    private function buildQuery(Request $request = null)
    {
        $query = Violation::query()
            ->with([
                'student.course',
                'student.year',
                'student.section',
                'violationType.category',
                'recorder'
            ]);

        if ($request) {
            if ($request->filled('course')) {
                $query->whereHas('student', fn($q) => $q->where('course_id', $request->course));
            }

            if ($request->filled('year')) {
                $query->whereHas('student', fn($q) => $q->where('year_id', $request->year));
            }

            if ($request->filled('category')) {
                $query->whereHas('violationType', fn($q) => $q->where('violation_category_id', $request->category));
            }

            if ($request->filled('violation_type')) {
                $query->where('violation_type_id', $request->violation_type);
            }

            if ($request->filled('start_date')) {
                $query->whereDate('violation_date', '>=', $request->start_date);
            }

            if ($request->filled('end_date')) {
                $query->whereDate('violation_date', '<=', $request->end_date);
            }

            if ($request->filled('search_student')) {
                $query->where('student_number', 'like', '%' . $request->search_student . '%');
            }
        }

        return $query;
    }

    private function getStatistics(Request $request = null)
    {
        $query = $this->buildQuery($request);

        return [
            'total' => $query->count(),
            'minor' => (clone $query)
                ->whereHas('violationType.category', fn($q) => $q->where('category_name', 'Minor'))
                ->count(),
            'major' => (clone $query)
                ->whereHas('violationType.category', fn($q) => $q->where('category_name', 'Major'))
                ->count(),
            'repeat_offenders' => (clone $query)
                ->select('student_number')
                ->groupBy('student_number')
                ->havingRaw('COUNT(*) > 1')
                ->count()
        ];
    }

    public function filter(Request $request)
    {
        $reports = $this->buildQuery($request)
            ->latest('violation_date')
            ->paginate(15);

        $statistics = $this->getStatistics($request);

        return view('admin.reports.index', [
            'reports' => $reports,
            'statistics' => $statistics,
            'courses' => Course::orderBy('course_name')->get(),
            'years' => Year::orderBy('year')->get(),
            'categories' => ViolationCategory::orderBy('category_name')->get(),
            'violationTypes' => ViolationType::orderBy('violation_type')->get()
        ]);
    }
}