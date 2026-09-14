<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Program;
use App\Models\ProgramReport;
use App\Models\Violation;
use App\Models\ViolationCategory;
use App\Models\ViolationType;
use App\Models\Year;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        /*
         * ONE Reports page with three tabs.
         *
         * ALL THREE datasets are built here, and all three panes are rendered.
         * That is deliberate: Bootstrap's tab plugin only toggles `display:none`
         * and never fetches content, so a tab whose data was not loaded would
         * show an EMPTY pane. Rendering everything also preserves in-progress
         * form values when the administrator switches tabs.
         *
         * Each dataset still comes from the same, unchanged per-tab process —
         * this method is only the page composer.
         */
        $activeReportsTab = $this->activeReportsTab($request);

        $resultsPerPage = in_array((int) $request->input('per_page'), [25, 50, 100], true)
            ? (int) $request->input('per_page')
            : 25;

        return view('admin.reports.show', array_merge(
            ['activeReportsTab' => $activeReportsTab],
            [
                // --- Violation Reports tab ---
                'statistics' => $this->getStatistics($request),
                'reports' => $this->buildQuery($request)
                    ->latest('violation_date')
                    ->paginate($resultsPerPage)
                    ->withQueryString(),
                'courses' => Course::orderBy('program_name')->get(),
                'years' => Year::orderBy('year')->get(),
                'categories' => ViolationCategory::orderBy('category_name')->get(),
                'violationTypes' => ViolationType::orderBy('violation_type')->get(),
            ],
            $this->programReportHistoryData($request),
            $this->graduationReviewData($request)
        ));
    }

    /**
     * Data for the "Graduation AI Report" tab.
     *
     * Delegates to GraduationReviewController so the tab and the standalone
     * graduation-review page share ONE dataset-building path. The service is
     * resolved from the container rather than re-implemented here.
     */
    protected function graduationReviewData(Request $request): array
    {
        return app(GraduationReviewController::class)->reviewData($request);
    }

    /**
     * The tabs this page understands. Kept in one place so a typo in ?tab= falls
     * back to the default rather than rendering an empty page.
     */
    protected const REPORTS_TABS = ['violation-reports', 'graduation-ai', 'history'];

    protected function activeReportsTab(Request $request): string
    {
        $tab = (string) $request->input('tab', '');

        // `ai-program` was the previous name of the graduation tab; keep it as an
        // alias so existing bookmarks/links do not fall back to the default.
        if ($tab === 'ai-program') {
            return 'graduation-ai';
        }

        return in_array($tab, self::REPORTS_TABS, true) ? $tab : 'violation-reports';
    }

    /**
     * Data for the "Report History" tab.
     *
     * Body lifted verbatim from ProgramReportController::history(), including the
     * authorization rule: a non-Super-Admin only ever sees their own reports.
     *
     * The dataset is bound as `$programReports` (NOT `$reports`) because the
     * page already uses `$reports` for the violation-report paginator. The tab
     * partial receives it explicitly as `$reports` so its own markup is unchanged.
     *
     * @return array{programReports: \Illuminate\Contracts\Pagination\LengthAwarePaginator, programs: \Illuminate\Database\Eloquent\Collection, isSuperAdmin: bool}
     */
    protected function programReportHistoryData(Request $request): array
    {
        $query = ProgramReport::with(['program', 'generatedBy'])
            ->latest('generated_at');

        $admin = Auth::guard('admin')->user();

        // Non-super admins only see their own generated reports.
        if ($admin && method_exists($admin, 'isItAdministrator') && ! $admin->isItAdministrator()) {
            $query->where('generated_by_admin_id', $admin->id);
        }

        if ($request->filled('program_id')) {
            $query->where('program_id', (int) $request->integer('program_id'));
        }

        if ($request->filled('report_type')) {
            $query->where('report_type', $request->string('report_type')->toString());
        }

        // The report_mode filter is deliberately kept: historical records still
        // carry report_mode = 'regular' (Program Sanction Report), and removing
        // the filter would make those records unfilterable. Only the AI Program
        // Report FORM no longer offers that mode.
        if ($request->filled('report_mode') && Schema::hasColumn('program_reports', 'report_mode')) {
            $query->where('report_mode', $request->string('report_mode')->toString());
        }

        $isSuperAdmin = $admin && method_exists($admin, 'isItAdministrator') && $admin->isItAdministrator();

        return [
            'programReports' => $query->paginate(15)->withQueryString(),
            // Deliberately NOT `programs`: the AI Program Report tab also needs a
            // `$programs` collection, and both panes are rendered on every request
            // (Bootstrap only toggles visibility). Sharing the name would let one
            // tab's guard pass using the other tab's data and then fail on a
            // variable it never received.
            'historyPrograms' => Program::orderBy('program_name')->get(),
            'isSuperAdmin' => $isSuperAdmin,
        ];
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
            ])
            // Only confirmed/legacy violations appear in official reports.
            ->effective();

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
                ->paginate(15)
                ->withQueryString();

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
            ->paginate(15)
            ->withQueryString();

        $statistics = $this->getStatistics($request);

        // Non-AJAX fallback (the filter form submitted without JS): render the
        // full page. All three panes are always rendered, so the other two tabs'
        // datasets must be supplied here too — otherwise they fall back to the
        // "load this tab" placeholder and the page looks half-broken.
        return view('admin.reports.show', array_merge(
            [
                'activeReportsTab' => 'violation-reports',
                'reports' => $reports,
                'statistics' => $statistics,
                'courses' => Course::orderBy('program_name')->get(),
                'years' => Year::orderBy('year')->get(),
                'categories' => ViolationCategory::orderBy('category_name')->get(),
                'violationTypes' => ViolationType::orderBy('violation_type')->get(),
            ],
            $this->programReportHistoryData($request),
            $this->graduationReviewData($request)
        ));
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

        $statistics = $this->getStatistics($request);
        $charts = $this->buildCharts($request);

        $spreadsheet = $this->buildReportSpreadsheet($reports, $statistics, $charts);
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

        $statistics = $this->getStatistics($request);
        $charts = $this->buildCharts($request);

        $html = view('admin.reports.pdf', [
            'reports' => $reports,
            'statistics' => $statistics,
            'charts' => $charts,
            'generatedAt' => now()->format('F d, Y, h:i a'),
            'filtersApplied' => $this->describeFilters($request),
        ])->render();

        $mpdf = new \Mpdf\Mpdf([
            'tempDir' => storage_path('framework/cache'),
            'margin_left' => 12,
            'margin_right' => 12,
            'margin_top' => 12,
            'margin_bottom' => 12,
        ]);

        $mpdf->WriteHTML($html);

        $fileName = 'violation-reports-' . now()->format('Ymd_His') . '.pdf';

        return response($mpdf->Output($fileName, 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ]);
    }

    public function print(Request $request)
    {
        $reports = $this->buildQuery($request)
            ->latest('violation_date')
            ->get();

        $filtersApplied = $this->describeFilters($request);

        return view('admin.reports.print', [
            'reports' => $reports,
            'generatedAt' => now()->format('F d, Y, h:i a'),
            'filtersApplied' => $filtersApplied,
        ]);
    }

    /**
     * Build a human-readable description of the active report filters.
     */
    private function describeFilters(Request $request): string
    {
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

        return empty($filters) ? 'None' : implode(' | ', $filters);
    }

    /**
     * How many bars the "Top N Most Common Violations" chart may show.
     *
     * The chart is labelled "Top 10 Most Common Violations", so the dataset is
     * capped here — server-side, before it reaches the view. Limiting in the view
     * (or hiding extra bars with CSS/JS) would still ship every type to the
     * browser and make the labels/data lengths inconsistent.
     */
    private const TOP_VIOLATIONS_LIMIT = 10;

    private function buildCharts(Request $request): array
    {
        $reports = $this->buildQuery($request)
            ->latest('violation_date')
            ->get();

        $monthly = $reports->groupBy(fn ($report) => Carbon::parse($report->violation_date)->format('M Y'));
        $categoryCounts = $reports->groupBy(fn ($report) => optional($report->violationType?->violationCategory)->category_name ?? 'Uncategorized');
        $violationCounts = $reports->groupBy(fn ($report) => optional($report->violationType)->violation_type ?? 'Unspecified');

        // Most common first, then keep only the top N. Ties are broken by label so
        // the order is deterministic between requests. When fewer than N types
        // exist, this simply returns all of them.
        $topViolations = $violationCounts
            ->map(fn ($items) => $items->count())
            ->sortByDesc(fn (int $count, string $label) => $count)
            ->take(self::TOP_VIOLATIONS_LIMIT);

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
                'labels' => $topViolations->keys()->values()->all(),
                'data' => $topViolations->values()->all(),
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

    private function buildReportSpreadsheet($reports, array $statistics = [], array $charts = []): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();

        // ---- Sheet 1: Summary + Charts ----
        $summarySheet = $spreadsheet->getActiveSheet();
        $summarySheet->setTitle('Summary');

        $summarySheet->setCellValue('A1', 'VIOLATION REPORT SUMMARY');
        $summarySheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $summarySheet->mergeCells('A1:D1');

        $summaryRows = [
            ['Total Violations', (int) ($statistics['total'] ?? $reports->count())],
            ['Minor Violations', (int) ($statistics['minor'] ?? 0)],
            ['Major Violations', (int) ($statistics['major'] ?? 0)],
            ['Repeat Offenders', (int) ($statistics['repeat_offenders'] ?? 0)],
            ['Generated At', now()->format('M d, Y h:i A')],
        ];

        $row = 3;
        foreach ($summaryRows as $summaryRow) {
            $summarySheet->setCellValue('A' . $row, $summaryRow[0]);
            $summarySheet->getStyle('A' . $row)->getFont()->setBold(true);
            $summarySheet->setCellValue('B' . $row, $summaryRow[1]);
            $row++;
        }

        // Monthly trend (chart data).
        $row += 1;
        $summarySheet->setCellValue('A' . $row, 'MONTHLY VIOLATION TREND');
        $summarySheet->getStyle('A' . $row)->getFont()->setBold(true);
        $row++;
        $summarySheet->setCellValue('A' . $row, 'Month');
        $summarySheet->setCellValue('B' . $row, 'Count');
        $summarySheet->getStyle('A' . $row . ':B' . $row)->getFont()->setBold(true);
        $row++;
        foreach (($charts['monthly']['labels'] ?? []) as $i => $label) {
            $summarySheet->setCellValue('A' . $row, $label);
            $summarySheet->setCellValue('B' . $row, (int) ($charts['monthly']['data'][$i] ?? 0));
            $row++;
        }

        // Category distribution (chart data).
        $row += 1;
        $summarySheet->setCellValue('A' . $row, 'CATEGORY DISTRIBUTION');
        $summarySheet->getStyle('A' . $row)->getFont()->setBold(true);
        $row++;
        $summarySheet->setCellValue('A' . $row, 'Category');
        $summarySheet->setCellValue('B' . $row, 'Count');
        $summarySheet->getStyle('A' . $row . ':B' . $row)->getFont()->setBold(true);
        $row++;
        foreach (($charts['categories']['labels'] ?? []) as $i => $label) {
            $summarySheet->setCellValue('A' . $row, $label);
            $summarySheet->setCellValue('B' . $row, (int) ($charts['categories']['data'][$i] ?? 0));
            $row++;
        }

        // Top violations (chart data).
        $row += 1;
        $summarySheet->setCellValue('A' . $row, 'TOP VIOLATIONS');
        $summarySheet->getStyle('A' . $row)->getFont()->setBold(true);
        $row++;
        $summarySheet->setCellValue('A' . $row, 'Violation');
        $summarySheet->setCellValue('B' . $row, 'Count');
        $summarySheet->getStyle('A' . $row . ':B' . $row)->getFont()->setBold(true);
        $row++;
        foreach (($charts['topViolations']['labels'] ?? []) as $i => $label) {
            $summarySheet->setCellValue('A' . $row, $label);
            $summarySheet->setCellValue('B' . $row, (int) ($charts['topViolations']['data'][$i] ?? 0));
            $row++;
        }

        $summarySheet->getColumnDimension('A')->setAutoSize(true);
        $summarySheet->getColumnDimension('B')->setAutoSize(true);

        // ---- Sheet 2: Violation Records ----
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Violations');

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

        // Make Summary the first visible sheet.
        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

}
