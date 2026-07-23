<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Violation;
use App\Models\ViolationCategory;
use App\Models\ViolationType;
use App\Models\Year;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $statistics = $this->getStatistics($request);

        $reports = $this->buildQuery($request)
            ->latest('violation_date')
            ->paginate(15);

        $courses = Course::orderBy('program_name')->get();
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
                'student.studentInfo.program',
                'student.studentInfo.year',
                'student.studentInfo.section',
                'violationType.violationCategory',
                'recorder',
            ]);

        if ($request) {
            if ($request->filled('course')) {
                $query->whereHas('student.studentInfo', fn ($q) => $q->where('program_id', $request->course));
            }

            if ($request->filled('year')) {
                $query->whereHas('student.studentInfo', fn ($q) => $q->where('year_id', $request->year));
            }

            if ($request->filled('category')) {
                $query->whereHas('violationType', fn ($q) => $q->where('violation_category_id', $request->category));
            }

            if ($request->filled('violation_type')) {
                $query->where('violation_type', $request->violation_type);
            }

            if ($request->filled('start_date')) {
                $query->whereDate('violation_date', '>=', $request->start_date);
            }

            if ($request->filled('end_date')) {
                $query->whereDate('violation_date', '<=', $request->end_date);
            }

            if ($request->filled('search_student')) {
                $query->where(function ($q) use ($request): void {
                    $q->where('student_number', 'like', '%' . $request->search_student . '%')
                        ->orWhereHas('student', fn ($studentQuery) => $studentQuery
                            ->where('first_name', 'like', '%' . $request->search_student . '%')
                            ->orWhere('last_name', 'like', '%' . $request->search_student . '%'));
                });
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
                ->whereHas('violationType.violationCategory', fn ($q) => $q->where('category_name', 'Minor'))
                ->count(),
            'major' => (clone $query)
                ->whereHas('violationType.violationCategory', fn ($q) => $q->where('category_name', 'Major'))
                ->count(),
            'repeat_offenders' => (clone $query)
                ->select('student_number')
                ->groupBy('student_number')
                ->havingRaw('COUNT(*) > 1')
                ->count(),
        ];
    }

    public function filter(Request $request)
    {
        if ($request->expectsJson() || $request->ajax()) {
            $reports = $this->buildQuery($request)
                ->latest('violation_date')
                ->paginate(15);

            $statistics = $this->getStatistics($request);
            $charts = $this->buildCharts($request);

            return response()->json([
                'statistics' => $statistics,
                'records' => view('admin.reports.partials.table-body', compact('reports'))->render(),
                'total' => $reports->total(),
                'charts' => $charts,
            ]);
        }

        $reports = $this->buildQuery($request)
            ->latest('violation_date')
            ->paginate(15);

        $statistics = $this->getStatistics($request);

        return view('admin.reports.index', [
            'reports' => $reports,
            'statistics' => $statistics,
            'courses' => Course::orderBy('program_name')->get(),
            'years' => Year::orderBy('year')->get(),
            'categories' => ViolationCategory::orderBy('category_name')->get(),
            'violationTypes' => ViolationType::orderBy('violation_type')->get(),
        ]);
    }

    public function export(Request $request)
    {
        $reports = $this->buildQuery($request)
            ->latest('violation_date')
            ->get();

        $fileName = 'violation-reports-' . now()->format('Ymd_His') . '.csv';
        $tempHandle = fopen('php://temp', 'r+');

        fputcsv($tempHandle, ['Student', 'Course', 'Violation', 'Category', 'Severity', 'Sanction', 'Date', 'Recorded By']);

        foreach ($reports as $report) {
            fputcsv($tempHandle, [
                trim(($report->student?->student_number ?? '') . ' ' . ($report->student?->last_name ?? '') . ', ' . ($report->student?->first_name ?? '')),
                optional($report->student?->studentInfo?->program)->program_name ?? '-',
                optional($report->violationType)->violation_type ?? '-',
                optional($report->violationType?->violationCategory)->category_name ?? '-',
                optional($report->violationType)->severity_level ?? '-',
                optional($report->sanction)->disciplinary_sanction ?? '-',
                $report->violation_date ? Carbon::parse($report->violation_date)->format('Y-m-d') : '-',
                optional($report->recorder)->first_name ?? '-',
            ]);
        }

        rewind($tempHandle);
        $csv = stream_get_contents($tempHandle);
        fclose($tempHandle);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ]);
    }

    public function print(Request $request)
    {
        $reports = $this->buildQuery($request)
            ->latest('violation_date')
            ->paginate(15);

        return view('admin.reports.print', compact('reports'));
    }

    private function buildCharts(Request $request): array
    {
        $reports = $this->buildQuery($request)
            ->latest('violation_date')
            ->get();

        $monthly = $reports->groupBy(fn ($report) => Carbon::parse($report->violation_date)->format('M Y'));
        $categoryCounts = $reports->groupBy(fn ($report) => optional($report->violationType?->violationCategory)->category_name ?? 'Uncategorized');
        $violationCounts = $reports->groupBy(fn ($report) => optional($report->violationType)->violation_type ?? 'Unspecified');

        return [
            'monthly' => [
                'labels' => $monthly->keys()->values()->all(),
                'data' => $monthly->map(fn ($items) => $items->count())->values()->all(),
            ],
            'categories' => [
                'labels' => $categoryCounts->keys()->values()->all(),
                'data' => $categoryCounts->map(fn ($items) => $items->count())->values()->all(),
            ],
            'topViolations' => [
                'labels' => $violationCounts->keys()->values()->all(),
                'data' => $violationCounts->map(fn ($items) => $items->count())->values()->all(),
            ],
        ];
    }
}
