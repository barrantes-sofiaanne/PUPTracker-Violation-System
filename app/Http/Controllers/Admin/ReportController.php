<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Violation;
use App\Models\Course;
use App\Models\Year;
use App\Models\ViolationCategory;
use Carbon\Carbon;

class ReportController extends Controller
{
    public function index()
{
    $statistics = $this->getStatistics();

    $reports = $this->buildQuery()
        ->latest('violation_date')
        ->paginate(15);

    $courses = Course::orderBy('course_name')->get();

    $years = Year::orderBy('year')->get();

    $categories = ViolationCategory::orderBy('category_name')->get();

    return view(
        'admin.reports.index',
        compact(
            'reports',
            'statistics',
            'courses',
            'years',
            'categories'
        )
    );
}
private function buildQuery(Request $request = null)
{
    return Violation::query()

        ->with([

            'student.course',

            'student.year',

            'student.section',

            'violationType.category',

            'recorder'

        ])

        ->when(
            $request?->course,
            function ($query, $course) {

                $query->whereHas(
                    'student',
                    fn($q) =>
                        $q->where(
                            'course_id',
                            $course
                        )
                );

            }
        )

        ->when(
            $request?->year,
            function ($query, $year) {

                $query->whereHas(
                    'student',
                    fn($q)=>
                        $q->where(
                            'year_id',
                            $year
                        )
                );

            }
        )

        ->when(
            $request?->category,
            function ($query,$category){

                $query->whereHas(

                    'violationType',

                    fn($q)=>

                        $q->where(

                            'category_id',

                            $category

                        )

                );

            }

        );
}

private function getStatistics(
    Request $request = null
)
{
    $query = $this
        ->buildQuery($request);

    return [

        'total'=>

            $query->count(),

        'minor'=>

            (clone $query)

            ->whereHas(

                'violationType.category',

                fn($q)=>

                    $q->where(

                        'category_name',

                        'Minor'

                    )

            )->count(),

        'major'=>

            (clone $query)

            ->whereHas(

                'violationType.category',

                fn($q)=>

                    $q->where(

                        'category_name',

                        'Major'

                    )

            )->count(),

        'repeat_offenders'=>

            (clone $query)

            ->select('student_number')

            ->groupBy('student_number')

            ->havingRaw(

                'COUNT(*) > 1'

            )

            ->count()

    ];
}
public function filter(Request $request)
{
    $reports = $this
        ->buildQuery($request)
        ->latest('violation_date')
        ->paginate(15);

    return response()->json([

        'statistics'=>$this->getStatistics($request),

        'charts'=>$this->chartData($request),

        'records'=>$reports

    ]);
}

private function chartData(
    Request $request = null
)
{
    return [

        'monthly'=>

            $this->monthlyTrend($request),

        'categories'=>

            $this->categoryDistribution($request),

        'topViolations'=>

            $this->topViolations($request)

    ];
}

private function monthlyTrend(Request $request = null)
{
    $query = $this->buildQuery($request);

    return $query
        ->selectRaw('MONTH(violation_date) as month, YEAR(violation_date) as year, COUNT(*) as total')
        ->groupBy('year', 'month')
        ->orderBy('year')
        ->orderBy('month')
        ->get();
}

private function categoryDistribution(Request $request = null)
{
    $query = $this->buildQuery($request);

    return $query
        ->selectRaw('violation_categories.category_name, COUNT(*) as total')
        ->join('violation_types', 'violations.violation_type_id', '=', 'violation_types.id')
        ->join('violation_categories', 'violation_types.category_id', '=', 'violation_categories.id')
        ->groupBy('violation_categories.category_name')
        ->get();
}

private function topViolations(Request $request = null)
{
    $query = $this->buildQuery($request);

    return $query
        ->selectRaw('violation_types.violation_name, COUNT(*) as total')
        ->join('violation_types', 'violations.violation_type_id', '=', 'violation_types.id')
        ->groupBy('violation_types.violation_name')
        ->orderByDesc('total')
        ->limit(10)
        ->get();
}
}