<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\ProgramReport;
use App\Models\User;
use App\Services\AIGraduationReviewService;
use App\Services\ProgramReportService;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Illuminate\Pagination\LengthAwarePaginator;

class ProgramReportController extends Controller
{
    public function __construct(
        protected ProgramReportService $programReport,
        protected AIGraduationReviewService $ai,
    ) {
    }

    /**
     * Backward-compatible entry point for the former standalone page.
     *
     * The Graduation AI Report now lives in Admin → Reports → Graduation AI
     * Report, which is the canonical UI. This keeps the historic URL working by
     * redirecting to that tab (query string preserved) instead of serving a
     * second copy of the same process, and instead of deleting a route that
     * bookmarks and internal links may depend on.
     */
    public function index(Request $request): \Illuminate\Http\RedirectResponse
    {
        return redirect()->route('admin.reports', array_merge(
            $request->query(),
            ['tab' => 'graduation-ai']
        ));
    }

    /**
     * Data for the Graduation AI Report feature.
     *
     * Composed by ReportController for the Reports tab, and reused by the
     * redirect target so the dataset is defined once.
     *
     * `ladderizedPrograms` doubles as the tab's render guard, so it must never
     * be renamed.
     */
    public function formData(): array
    {
        return [
            'programs' => Program::orderBy('program_name')->get(),
            'ladderizedPrograms' => config('graduation_review.graduating.ladderized_programs', ['BSOA', 'BSIT']),
        ];
    }

    /**
     * Whether a program supports the Ladderized filter (BSOA / BSIT).
     */
    protected function supportsLadderized(Program $program): bool
    {
        $programs = config('graduation_review.graduating.ladderized_programs', ['BSOA', 'BSIT']);

        $name = strtoupper(trim((string) $program->program_name));

        return in_array($name, array_map('strtoupper', $programs), true);
    }

    /**
     * Preview the graduating students for the selected program — returned as
     * JSON for the AJAX preview list in the report form.
     *
     * Automatic eligibility uses the program's graduating YEAR LEVEL (4th Year
     * for Bachelor's / Ladderized Bachelor, 3rd Year for Diploma); specific
     * students added manually by number are always included.
     */
    public function previewGraduating(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'program_id' => ['required', 'integer', 'exists:program_tbl,program_id'],
            'additional_students' => ['nullable', 'array'],
            'additional_students.*' => ['nullable', 'string', 'max:50'],
            'ladderized' => ['nullable', 'in:0,1'],
            'exclude_students' => ['nullable', 'array'],
            'exclude_students.*' => ['nullable', 'string', 'max:50'],
        ]);

        $program = Program::findOrFail((int) $validated['program_id']);
        $additional = array_values(array_filter(
            array_map(fn ($v) => trim((string) $v), (array) ($validated['additional_students'] ?? [])),
            fn ($v) => $v !== ''
        ));
        $ladderized = isset($validated['ladderized']) && $validated['ladderized'] !== ''
            ? (int) $validated['ladderized']
            : null;

        // Only apply the ladderized flag filter for BSOA/BSIT.
        if (! $this->supportsLadderized($program)) {
            $ladderized = null;
        }

        // Removal is preview-only state: intersect the requested exclusions with
        // the students this administrator is actually authorised to see, so a
        // stray/forged number cannot suppress an unrelated student.
        $excluded = $this->authorizeExcludedStudents($program, (array) ($validated['exclude_students'] ?? []), $ladderized, $additional);

        try {
            $students = $this->programReport->previewGraduating($program, [], $ladderized, $additional, $excluded);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'total' => $students->count(),
            'ladderized_filter' => $ladderized,
            'students' => $students->map(fn ($s) => [
                'student_number' => $s->student_number,
                'name' => trim(($s->last_name ?? '') . ', ' . ($s->first_name ?? '') . ' ' . ($s->middle_name ?? '')),
                'program' => $s->studentInfo?->program?->program_name ?? '-',
                'year' => $s->studentInfo?->year?->year ?? '-',
                'section' => $s->studentInfo?->section?->section_name ?? '-',
                'status' => $s->studentInfo?->studentStatus?->status_name ?? '-',
                // Lets the preview label the ladderized group. The server is the
                // only place this flag is authoritative.
                'is_ladderized' => (bool) ($s->studentInfo?->ladderized ?? false),
                // True when the student is in the report because the
                // administrator added them by hand rather than by eligibility.
                'is_added' => in_array((string) $s->student_number, $additional, true),
            ]),
        ]);
    }

    /**
     * Intersect administrator-requested exclusions with the students who are
     * genuinely part of the authorised preview cohort for this program.
     *
     * This is the security boundary for the "remove a student from the preview"
     * feature:
     *   - the client list is NEVER trusted as-is;
     *   - a number that is not in the eligible cohort is silently dropped, so it
     *     can neither suppress an unrelated student nor inject one;
     *   - the eligible cohort itself is recomputed from the database on every
     *     request.
     *
     * Purely a narrowing operation: no student record is read-modify-written and
     * nothing is deleted.
     *
     * @param  array<int, mixed>  $requested
     * @return array<int, string>
     */
    protected function authorizeExcludedStudents(Program $program, array $requested, ?int $ladderized, array $additionalStudents): array
    {
        $clean = array_values(array_unique(array_filter(
            array_map(fn ($v) => trim((string) $v), $requested),
            fn ($v) => $v !== ''
        )));

        if ($clean === []) {
            return [];
        }

        // Recompute the authorised cohort WITHOUT the exclusions applied, then
        // keep only the requested numbers that actually appear in it.
        try {
            $eligible = $this->programReport->previewGraduating($program, [], $ladderized, $additionalStudents)
                ->pluck('student_number')
                ->all();
        } catch (InvalidArgumentException $exception) {
            return [];
        }

        return array_values(array_intersect($clean, $eligible));
    }

    /**
     * AJAX student search for the graduating report "add specific student"
     * control. Returns matching student-role users for the selected program.
     */
    public function searchStudents(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'program_id' => ['required', 'integer', 'exists:program_tbl,program_id'],
            'search' => ['required', 'string', 'max:100'],
        ]);

        $keyword = '%' . trim($validated['search']) . '%';

        $students = User::query()
            ->with(['studentInfo.program', 'studentInfo.year', 'studentInfo.section'])
            ->where('roles_id', 2)
            ->whereHas('studentInfo', fn ($q) => $q->where('program_id', (int) $validated['program_id']))
            ->where(function ($q) use ($keyword): void {
                $q->where('student_number', 'like', $keyword)
                    ->orWhere('first_name', 'like', $keyword)
                    ->orWhere('last_name', 'like', $keyword);
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit(15)
            ->get();

        return response()->json([
            'students' => $students->map(fn ($s) => [
                'student_number' => $s->student_number,
                'name' => trim(($s->last_name ?? '') . ', ' . ($s->first_name ?? '') . ' ' . ($s->middle_name ?? '')),
                'program' => $s->studentInfo?->program?->program_name ?? '-',
                'year' => $s->studentInfo?->year?->year ?? '-',
                'section' => $s->studentInfo?->section?->section_name ?? '-',
            ]),
        ]);
    }

    /**
     * Generate (and persist) the program-level report.
     *
     * Laravel computes ALL factual statistics for the whole program; DeepSeek
     * receives ONE aggregated dataset and returns the narrative.
     */
    public function generate(Request $request): \Illuminate\Http\RedirectResponse
    {
        // Preserve the existing regular-report POST contract while the UI
        // introduces the explicit report mode selector.
        $request->merge(['report_mode' => $request->input('report_mode', 'regular')]);

        $validated = $request->validate([
            'program_id' => ['required', 'integer', 'exists:program_tbl,program_id'],
            'report_mode' => ['required', 'in:regular,graduating'],
            'academic_year' => ['nullable', 'string', 'max:20', 'required_if:report_mode,regular'],
            'additional_students' => ['nullable', 'array'],
            'additional_students.*' => ['nullable', 'string', 'max:50'],
            // Students the administrator REMOVED from the graduating preview.
            // Only ever used to NARROW this request's candidate set, and
            // re-authorised server-side below. Purely an input list — no
            // student record is modified or deleted.
            'excluded_students' => ['nullable', 'array'],
            'excluded_students.*' => ['nullable', 'string', 'max:50'],
            'ladderized' => ['nullable', 'in:0,1'],
            'report_type' => ['required', 'in:full,violations,outstanding,manual_review'],
        ]);

        $program = Program::findOrFail((int) $validated['program_id']);
        $reportMode = $validated['report_mode'];
        $academicYear = $reportMode === 'graduating' ? null : trim((string) $validated['academic_year']);
        $reportType = $validated['report_type'];
        $additionalStudents = $reportMode === 'graduating'
            ? array_values(array_filter(
                array_map(fn ($v) => trim((string) $v), (array) ($validated['additional_students'] ?? [])),
                fn ($v) => $v !== ''
            ))
            : [];
        // Ladderized filter applies only in graduating mode for BSOA/BSIT.
        $ladderized = ($reportMode === 'graduating' && isset($validated['ladderized']) && $validated['ladderized'] !== '' && $this->supportsLadderized($program))
            ? (int) $validated['ladderized']
            : null;

        $excludedStudents = $reportMode === 'graduating'
            ? $this->authorizeExcludedStudents($program, (array) ($validated['excluded_students'] ?? []), $ladderized, $additionalStudents)
            : [];

        try {
            $statistics = $this->programReport->aggregate(
                $program,
                $academicYear,
                $reportType,
                $reportMode,
                [],
                $ladderized,
                $additionalStudents,
                $excludedStudents
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['graduating_years' => $exception->getMessage()]);
        }

        $adminId = Auth::guard('admin')->id();
        // For graduating reports the display label is the graduating YEAR
        // LEVEL derived from the program (e.g. "4th Year").
        $graduatingLevel = $statistics['graduating_year'];
        $graduatingLabel = $graduatingLevel !== null
            ? $this->ordinalYearLabel((int) $graduatingLevel)
            : null;

        $reportData = [
            'program_id' => $program->program_id,
            'academic_year' => $reportMode === 'graduating'
                ? $graduatingLabel
                : $academicYear,
            'report_type' => $reportType,
            'report_mode' => $reportMode,
            'degree_type' => $statistics['degree_type'],
            'graduating_year' => $statistics['graduating_year'],
            'admission_years' => [],
            'ladderized' => $ladderized,
            'additional_students' => $reportMode === 'graduating' ? $additionalStudents : [],
            'excluded_students' => $excludedStudents,
            'generated_by_admin_id' => $adminId,
            'generated_at' => now(),
            'report_status' => 'generated',
            'statistics_json' => $statistics,
            'violation_breakdown' => [
                'categories' => $statistics['violation_categories'],
                'types' => $statistics['violation_types'],
            ],
            'repeated_violations' => $statistics['repeated_violations'],
            'outstanding_students' => $statistics['outstanding_students'],
            'manual_review_students' => $statistics['manual_review_students'],
            'ai_failed' => ! $this->ai->isAvailable(),
        ];

        foreach (['report_mode', 'degree_type', 'graduating_year', 'admission_years', 'ladderized', 'additional_students', 'excluded_students'] as $column) {
            if (! Schema::hasColumn('program_reports', $column)) {
                unset($reportData[$column]);
            }
        }

        $report = ProgramReport::create($reportData);

        $this->audit(
            $adminId,
            'Generated Program Report',
            ucfirst($reportMode) . ' report for program #' . $program->program_id . '.'
        );

        return redirect()
            ->route('admin.program-report.show', $report->report_id)
            ->with('success', 'Factual program report generated successfully.');
    }

    public function generateAi(Request $request, int $reportId): \Illuminate\Http\RedirectResponse
    {
        $report = ProgramReport::with('program')->findOrFail($reportId);
        $this->authorizeReport($report);

        if (! $this->ai->isAvailable()) {
            return back()->with('warning', 'AI-assisted summary is temporarily unavailable. The factual disciplinary report is still available for review.');
        }

        $statistics = $report->statistics_json ?? [];
        $students = collect($statistics['students'] ?? []);
        if ($students->isEmpty()) {
            return back()->with('warning', 'No factual student records are available for AI analysis.');
        }

        $narratives = [];
        $chunkSize = max(1, (int) config('graduation_review.ai.batch_size', 50));

        try {
            foreach ($students->chunk($chunkSize) as $chunk) {
                $dataset = $this->aiDataset($statistics);
                $dataset['students'] = $chunk->values()->all();
                $narratives[] = $this->ai->generateProgramAnalysis($dataset)['narrative'];
            }
        } catch (\Throwable $exception) {
            Log::warning('AI program summary failed; factual report remains available.', [
                'report_id' => $report->report_id,
                'error' => $exception->getMessage(),
            ]);

            return back()->with('warning', 'AI-assisted summary is temporarily unavailable. The factual disciplinary report is still available for review.');
        }

        $report->update([
            'ai_narrative' => implode("\n\n", $narratives),
            'ai_model' => (string) config('graduation_review.ai.model'),
            'ai_failed' => false,
            'report_status' => 'generated',
        ]);

        $this->audit(
            Auth::guard('admin')->id(),
            'Generated AI Program Summary',
            'Generated AI-assisted summary for program report #' . $report->report_id . '.'
        );

        return back()->with('success', 'AI-assisted summary generated from the factual report.');
    }

    /**
     * View a generated report (admin sees own; super admin sees all via history).
     */
    public function show(Request $request, int $reportId): View
    {
        $report = ProgramReport::with(['program', 'generatedBy'])
            ->findOrFail($reportId);

        $this->authorizeReport($report);

        $stats = $report->statistics_json ?? [];
        $students = collect($stats['students'] ?? []);
        $page = max(1, (int) $request->input('students_page', 1));
        $studentPage = new LengthAwarePaginator(
            $students->forPage($page, 50)->values(),
            $students->count(),
            50,
            $page,
            ['path' => route('admin.program-report.show', $report->report_id), 'query' => $request->query()]
        );

        return view('admin.program-report.show', compact('report', 'studentPage'));
    }

    /**
     * Download the report as PDF.
     */
    public function pdf(Request $request, int $reportId)
    {
        $report = ProgramReport::with(['program', 'generatedBy'])
            ->findOrFail($reportId);

        $this->authorizeReport($report);

        $this->audit(
            Auth::guard('admin')->id(),
            'Exported Program Report',
            'Exported program report #' . $report->report_id . ' as PDF.'
        );

        $html = view('admin.program-report.pdf', compact('report'))->render();

        $mpdf = new \Mpdf\Mpdf([
            'tempDir' => storage_path('framework/cache'),
            'margin_left' => 14,
            'margin_right' => 14,
            'margin_top' => 14,
            'margin_bottom' => 14,
        ]);

        $mpdf->WriteHTML($html);

        // Graduation AI reports use their own filename convention:
        //   Graduation_Ai_Summary_<Program>_<Year>.pdf
        // The year is the value the report already carries (for graduating
        // reports that is the ordinal label, e.g. "4th Year"). The existing
        // sanitisation is kept, so spaces/special characters cannot produce an
        // invalid filename.
        $appliedYear = $report->academic_year
            ?: ((int) ($report->graduating_year ?? 0) > 0 ? $this->ordinalYearLabel((int) $report->graduating_year) : '');

        if ($report->report_mode === 'graduating') {
            $filename = 'Graduation_Ai_Summary_' . $report->program?->program_name . '_' . $appliedYear . '.pdf';
        } else {
            $filename = 'program-report-' . $report->program?->program_name . '-' . $report->academic_year . '.pdf';
        }

        $filename = preg_replace('/[^A-Za-z0-9\-_.]/', '-', $filename);

        return response($mpdf->Output($filename, 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Report history — any admin sees reports; Super Admin sees ALL.
     *
     * Was the standalone page; that page is now the "Report History" tab of
     * Admin → Reports, so this redirects (preserving the query string, including
     * program_id / report_type / report_mode filters and the page number).
     */
    public function history(Request $request): \Illuminate\Http\RedirectResponse
    {
        return redirect()->route('admin.reports', array_merge(
            $request->query(),
            ['tab' => 'history']
        ));
    }

    /**
     * Convert a numeric year level (e.g. 4) into an ordinal label such as
     * "4th Year". Used for graduating reports whose display label is the
     * program's graduating year level.
     */
    protected function ordinalYearLabel(int $level): string
    {
        $suffix = match (true) {
            $level % 100 >= 11 && $level % 100 <= 13 => 'th',
            $level % 10 === 1 => 'st',
            $level % 10 === 2 => 'nd',
            $level % 10 === 3 => 'rd',
            default => 'th',
        };

        return $level . $suffix . ' Year';
    }

    /**
     * Build the aggregated dataset sent to DeepSeek (single request).
     */
    protected function aiDataset(array $statistics): array
    {
        return [
            'program' => $statistics['program'],
            'academic_year' => $statistics['academic_year'],
            'report_mode' => $statistics['report_mode'],
            'degree_type' => $statistics['degree_type'],
            'graduating_year' => $statistics['graduating_year'],
            'graduating_students' => $statistics['cohort_total'],
            'students_with_no_violations' => $statistics['students_with_no_violations'],
            'students_with_completed_sanctions' => $statistics['students_with_completed_sanctions'],
            'students_with_pending_sanctions' => $statistics['students_with_pending_sanctions'],
            'students_with_outstanding_sanctions' => $statistics['students_with_outstanding_sanctions'],
            'students_with_repeated_violations' => $statistics['students_with_repeated_violations'],
            'students_requiring_manual_review' => $statistics['students_requiring_manual_review'],
            'total_violation_records' => $statistics['total_violation_records'],
            'violation_categories' => $statistics['violation_categories'],
            'violation_types' => $statistics['violation_types'],
            'students' => $statistics['students'],
        ];
    }

    /**
     * Super Admin can view all reports; other admins only their own.
     */
    protected function authorizeReport(ProgramReport $report): void
    {
        $admin = Auth::guard('admin')->user();

        if ($admin && method_exists($admin, 'isItAdministrator') && $admin->isItAdministrator()) {
            return;
        }

        abort_unless($admin && (int) $report->generated_by_admin_id === (int) $admin->id, 403);
    }

    protected function audit(?int $adminId, string $action, string $description): void
    {
        if (Schema::hasTable('audit_logs')) {
            AuditLog::create([
                'actor_type' => 'admin',
                'actor_id' => $adminId,
                'action' => $action,
                'module' => 'Program Reports',
                'description' => $description,
            ]);
        }
    }
}
