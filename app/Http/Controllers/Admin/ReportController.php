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
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

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
                $selectedViolationType = (string) $request->violation_type;

                $query->where(function ($violationQuery) use ($selectedViolationType): void {
                    // Support both data styles in violation_tbl.violation_type:
                    // some records store the ID, others store the violation type text.
                    $violationQuery->where('violation_type', $selectedViolationType)
                        ->orWhereHas('violationType', function ($typeQuery) use ($selectedViolationType): void {
                            $typeQuery->where('violation_type_id', $selectedViolationType)
                                ->orWhere('violation_type', $selectedViolationType);
                        });
                });
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

    public function assistant(Request $request)
    {
        $validated = $request->validate([
            'prompt' => ['required', 'string', 'max:280'],
        ]);

        $prompt = trim($validated['prompt']);

        $records = $this->buildQuery($request)
            ->latest('violation_date')
            ->get();

        $total = $records->count();

        $topCategories = $records
            ->groupBy(fn ($report) => optional($report->violationType?->violationCategory)->category_name ?? 'Uncategorized')
            ->map(fn ($items) => $items->count())
            ->sortDesc()
            ->take(3);

        $topTypes = $records
            ->groupBy(fn ($report) => optional($report->violationType)->violation_type ?? 'Unspecified')
            ->map(fn ($items) => $items->count())
            ->sortDesc()
            ->take(5);

        $monthly = $records
            ->groupBy(fn ($report) => Carbon::parse($report->violation_date)->format('Y-m'))
            ->map(fn ($items) => $items->count())
            ->sortKeys();

        $trend = $this->detectTrend($monthly);
        $focus = $this->inferTopicFocus($prompt);

        $summary = $total === 0
            ? 'No records match the current filters. Try broadening your filters and regenerate the report.'
            : "Generated based on your request and active filters. Total matching records: {$total}. Overall trend: {$trend}.";

        $highlights = [
            'Total records: ' . $total,
            'Trend: ' . $trend,
        ];

        if ($topCategories->isNotEmpty()) {
            $highlights[] = 'Top category: ' . $topCategories->keys()->first() . ' (' . (int) $topCategories->first() . ')';
        }

        if ($topTypes->isNotEmpty()) {
            $highlights[] = 'Top violation: ' . $topTypes->keys()->first() . ' (' . (int) $topTypes->first() . ')';
        }

        return response()->json([
            'title' => 'AI Report Summary',
            'topic' => $prompt,
            'focus' => $focus,
            'summary' => $summary,
            'highlights' => $highlights,
            'top_categories' => $topCategories->map(fn ($count, $name) => [
                'name' => $name,
                'count' => (int) $count,
            ])->values(),
            'top_violations' => $topTypes->map(fn ($count, $name) => [
                'name' => $name,
                'count' => (int) $count,
            ])->values(),
            'recommended_actions' => $this->recommendedActions($focus, $trend, $total),
            'generated_at' => now()->format('M d, Y h:i A'),
        ]);
    }

    public function export(Request $request)
    {
        $reports = $this->buildQuery($request)
            ->latest('violation_date')
            ->get();

        $spreadsheet = $this->buildReportSpreadsheet($reports);
        $writer = new Xlsx($spreadsheet);

        $fileName = 'violation-reports-' . now()->format('Ymd_His') . '.xlsx';
        $tempFile = tempnam(sys_get_temp_dir(), 'violation_reports_xlsx_');

        $writer->save($tempFile);

        return response()
            ->download(
                $tempFile,
                $fileName,
                ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
            )
            ->deleteFileAfterSend(true);
    }

    public function exportPdf(Request $request)
    {
        $reports = $this->buildQuery($request)
            ->latest('violation_date')
            ->get();

        $spreadsheet = $this->buildReportSpreadsheet($reports);
        $writer = IOFactory::createWriter($spreadsheet, 'Mpdf');

        $fileName = 'violation-reports-' . now()->format('Ymd_His') . '.pdf';
        $tempFile = tempnam(sys_get_temp_dir(), 'violation_reports_pdf_');

        $writer->save($tempFile);

        return response()
            ->download($tempFile, $fileName, ['Content-Type' => 'application/pdf'])
            ->deleteFileAfterSend(true);
    }

    public function print(Request $request)
    {
        $reports = $this->buildQuery($request)
            ->latest('violation_date')
            ->get();

        $filters = [];

        if ($request->filled('course')) {
            $courseName = Course::query()
                ->where('program_id', $request->course)
                ->value('program_name');
            $filters[] = 'Course: ' . ($courseName ?: $request->course);
        }

        if ($request->filled('year')) {
            $yearLabel = Year::query()
                ->where('year_id', $request->year)
                ->value('year');
            $filters[] = 'Year: ' . ($yearLabel ?: $request->year);
        }

        if ($request->filled('category')) {
            $categoryName = ViolationCategory::query()
                ->where('violation_category_id', $request->category)
                ->value('category_name');
            $filters[] = 'Category: ' . ($categoryName ?: $request->category);
        }

        if ($request->filled('violation_type')) {
            $typeName = ViolationType::query()
                ->where('violation_type_id', $request->violation_type)
                ->value('violation_type');
            $filters[] = 'Violation Type: ' . ($typeName ?: $request->violation_type);
        }

        if ($request->filled('start_date')) {
            $filters[] = 'Start Date: ' . Carbon::parse($request->start_date)->format('M d, Y');
        }

        if ($request->filled('end_date')) {
            $filters[] = 'End Date: ' . Carbon::parse($request->end_date)->format('M d, Y');
        }

        if ($request->filled('search_student')) {
            $filters[] = 'Student Search: ' . $request->search_student;
        }

        $filtersApplied = empty($filters) ? 'None' : implode(' | ', $filters);

        return view('admin.reports.print', [
            'reports' => $reports,
            'generatedAt' => now()->format('F d, Y, h:i a'),
            'filtersApplied' => $filtersApplied,
        ]);
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

    private function detectTrend($monthly): string
    {
        if ($monthly->count() < 2) {
            return 'Stable';
        }

        $lastTwo = $monthly->values()->slice(-2)->values();
        $previous = (int) ($lastTwo[0] ?? 0);
        $latest = (int) ($lastTwo[1] ?? 0);

        if ($latest > $previous) {
            return 'Increasing';
        }

        if ($latest < $previous) {
            return 'Decreasing';
        }

        return 'Stable';
    }

    private function inferTopicFocus(string $prompt): string
    {
        $value = strtolower($prompt);

        if (str_contains($value, 'trend') || str_contains($value, 'monthly')) {
            return 'Trend Analysis';
        }

        if (str_contains($value, 'category') || str_contains($value, 'major') || str_contains($value, 'minor')) {
            return 'Category Analysis';
        }

        if (str_contains($value, 'course') || str_contains($value, 'year') || str_contains($value, 'section')) {
            return 'Population Segment Analysis';
        }

        return 'General Compliance Analysis';
    }

    private function recommendedActions(string $focus, string $trend, int $total): array
    {
        if ($total === 0) {
            return [
                'Broaden your filters (date, category, or student search).',
                'Verify that records exist for the selected period.',
            ];
        }

        $actions = [];

        if ($trend === 'Increasing') {
            $actions[] = 'Review recent increases and coordinate with concerned units.';
        }

        if ($focus === 'Category Analysis') {
            $actions[] = 'Prioritize corrective actions for the dominant category.';
        }

        if ($focus === 'Population Segment Analysis') {
            $actions[] = 'Coordinate with course/year advisers for targeted interventions.';
        }

        if (empty($actions)) {
            $actions[] = 'Export this report and include it in your weekly compliance briefing.';
            $actions[] = 'Monitor the same request again next period for comparison.';
        }

        return $actions;
    }

    private function buildReportSpreadsheet($reports): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = [
            'Student Number',
            'Student Name',
            'Course',
            'Violation',
            'Category',
            'Offense/Severity',
            'Sanction',
            'Date',
            'Recorded By',
        ];

        $sheet->fromArray($headers, null, 'A1');

        $row = 2;
        foreach ($reports as $report) {
            $sheet->setCellValue('A' . $row, $report->student?->student_number ?? '-');
            $sheet->setCellValue(
                'B' . $row,
                trim((string) ($report->student?->last_name ?? '') . ', ' . (string) ($report->student?->first_name ?? ''))
            );
            $sheet->setCellValue('C' . $row, optional($report->student?->studentInfo?->program)->program_name ?? '-');
            $sheet->setCellValue('D' . $row, optional($report->violationType)->violation_type ?: ($report->violation_type ?: '-'));
            $sheet->setCellValue('E' . $row, optional($report->violationType?->violationCategory)->category_name ?? '-');
            $sheet->setCellValue('F' . $row, optional($report->violationType)->severity_level ?? '-');
            $sheet->setCellValue('G' . $row, optional($report->sanction)->disciplinary_sanction ?? '-');
            $sheet->setCellValue(
                'H' . $row,
                $report->violation_date ? Carbon::parse($report->violation_date)->format('M d, Y h:i A') : '-'
            );
            $sheet->setCellValue('I' . $row, $report->recorded_by_display ?? '-');
            $row++;
        }

        $sheet->getStyle('A1:I1')->getFont()->setBold(true);
        $sheet->freezePane('A2');

        foreach (range('A', 'I') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return $spreadsheet;
    }

}
