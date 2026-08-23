<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\ProgramReport;
use App\Services\AIGraduationReviewService;
use App\Services\ProgramReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class ProgramReportController extends Controller
{
    public function __construct(
        protected ProgramReportService $programReport,
        protected AIGraduationReviewService $ai,
    ) {
    }

    /**
     * Program report page with the selection form.
     */
    public function index(): View
    {
        $programs = Program::orderBy('program_name')->get();

        return view('admin.program-report.index', compact('programs'));
    }

    /**
     * Generate (and persist) the program-level report.
     *
     * Laravel computes ALL factual statistics for the whole program; DeepSeek
     * receives ONE aggregated dataset and returns the narrative.
     */
    public function generate(Request $request): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'program_id' => ['required', 'integer', 'exists:program_tbl,program_id'],
            'academic_year' => ['required', 'string', 'max:20'],
            'report_type' => ['required', 'in:full,violations,outstanding,manual_review'],
        ]);

        $program = Program::findOrFail((int) $validated['program_id']);
        $academicYear = trim($validated['academic_year']);
        $reportType = $validated['report_type'];

        $statistics = $this->programReport->aggregate($program, $academicYear, $reportType);

        $adminId = Auth::guard('admin')->id();
        $model = (string) config('graduation_review.ai.model');
        $aiFailed = false;
        $narrative = null;

        if ($this->ai->isAvailable()) {
            try {
                $result = $this->ai->generateProgramAnalysis($this->aiDataset($statistics));
                $narrative = $result['narrative'];
            } catch (\Throwable $e) {
                Log::warning('AI program narrative failed; keeping Laravel statistics.', [
                    'program_id' => $program->program_id,
                    'error' => $e->getMessage(),
                ]);
                $aiFailed = true;
            }
        } else {
            $aiFailed = true;
        }

        $report = ProgramReport::create([
            'program_id' => $program->program_id,
            'academic_year' => $academicYear,
            'report_type' => $reportType,
            'generated_by_admin_id' => $adminId,
            'generated_at' => now(),
            'report_status' => $aiFailed ? 'ai_failed' : 'generated',
            'statistics_json' => $statistics,
            'violation_breakdown' => [
                'categories' => $statistics['violation_categories'],
                'types' => $statistics['violation_types'],
            ],
            'repeated_violations' => $statistics['repeated_violations'],
            'outstanding_students' => $statistics['outstanding_students'],
            'manual_review_students' => $statistics['manual_review_students'],
            'ai_narrative' => $narrative,
            'ai_model' => $model,
            'ai_failed' => $aiFailed,
        ]);

        return redirect()
            ->route('admin.program-report.show', $report->report_id)
            ->with($aiFailed ? 'warning' : 'success', $aiFailed
                ? 'Report generated. The AI narrative was unavailable, but the factual statistics are complete.'
                : 'Report generated successfully.');
    }

    /**
     * View a generated report (admin sees own; super admin sees all via history).
     */
    public function show(Request $request, int $reportId): View
    {
        $report = ProgramReport::with(['program', 'generatedBy'])
            ->findOrFail($reportId);

        $this->authorizeReport($report);

        return view('admin.program-report.show', compact('report'));
    }

    /**
     * Download the report as PDF.
     */
    public function pdf(Request $request, int $reportId)
    {
        $report = ProgramReport::with(['program', 'generatedBy'])
            ->findOrFail($reportId);

        $this->authorizeReport($report);

        $html = view('admin.program-report.pdf', compact('report'))->render();

        $mpdf = new \Mpdf\Mpdf([
            'tempDir' => storage_path('framework/cache'),
            'margin_left' => 14,
            'margin_right' => 14,
            'margin_top' => 14,
            'margin_bottom' => 14,
        ]);

        $mpdf->WriteHTML($html);

        $filename = 'program-report-' . $report->program?->program_name . '-' . $report->academic_year . '.pdf';
        $filename = preg_replace('/[^A-Za-z0-9\-_.]/', '-', $filename);

        return response($mpdf->Output($filename, 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Report history — any admin sees reports; Super Admin sees ALL.
     */
    public function history(Request $request): View
    {
        $query = ProgramReport::with(['program', 'generatedBy'])
            ->latest('generated_at');

        $admin = Auth::guard('admin')->user();

        // Non-super admins only see their own generated reports.
        if ($admin && method_exists($admin, 'isItAdministrator') && ! $admin->isItAdministrator()) {
            $query->where('generated_by_admin_id', $admin->getKey());
        }

        if ($request->filled('program_id')) {
            $query->where('program_id', (int) $request->integer('program_id'));
        }

        if ($request->filled('report_type')) {
            $query->where('report_type', $request->string('report_type')->toString());
        }

        $reports = $query->paginate(15)->withQueryString();
        $programs = Program::orderBy('program_name')->get();

        $isSuperAdmin = $admin && method_exists($admin, 'isItAdministrator') && $admin->isItAdministrator();

        return view('admin.program-report.history', compact('reports', 'programs', 'isSuperAdmin'));
    }

    /**
     * Build the aggregated dataset sent to DeepSeek (single request).
     */
    protected function aiDataset(array $statistics): array
    {
        return [
            'program' => $statistics['program'],
            'academic_year' => $statistics['academic_year'],
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

        abort_unless($admin && (int) $report->generated_by_admin_id === (int) $admin->getKey(), 403);
    }
}
