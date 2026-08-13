<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AIGraduationReview;
use App\Models\Program;
use App\Models\StudentSanctionRecord;
use App\Models\User;
use App\Models\Violation;
use App\Models\Year;
use App\Services\AIGraduationReviewService;
use App\Services\GraduationReviewScreeningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class GraduationReviewController extends Controller
{
    public function __construct(
        protected GraduationReviewScreeningService $screening,
        protected AIGraduationReviewService $ai,
    ) {
    }

    /**
     * Graduation review page + consolidated batch report.
     */
    public function index(Request $request): View
    {
        $programs = Program::orderBy('program_name')->get();
        $years = Year::orderBy('year')->get();

        $students = $this->screening->graduatingStudentsQuery()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $programFilter = $request->integer('program_id');
        if ($programFilter > 0) {
            $students = $students->filter(function (User $student) use ($programFilter) {
                return (int) ($student->studentInfo?->program_id ?? $student->program_id) === $programFilter;
            })->values();
        }

        $yearFilter = $request->integer('year_id');
        if ($yearFilter > 0) {
            $students = $students->filter(function (User $student) use ($yearFilter) {
                return (int) ($student->studentInfo?->year_id ?? $student->year_id) === $yearFilter;
            })->values();
        }

        $screened = $students
            ->map(fn (User $student) => $this->screening->screenStudent($student))
            ->values();

        $report = $this->buildReport($screened);

        return view('admin.graduation-review.index', compact(
            'programs',
            'years',
            'programFilter',
            'yearFilter',
            'screened',
            'report',
            'students',
        ));
    }

    /**
     * Detail view for a single student with supporting records + AI summary.
     */
    public function show(Request $request, string $student_number): View
    {
        $student = User::query()
            ->with([
                'studentInfo.program',
                'studentInfo.year',
                'studentInfo.section',
                'studentInfo.studentStatus',
                'status',
            ])
            ->where('student_number', $student_number)
            ->where('roles_id', 2)
            ->firstOrFail();

        $screening = $this->screening->screenStudent($student);

        $savedReview = AIGraduationReview::latestFor($student_number);

        return view('admin.graduation-review.show', compact('student', 'screening', 'savedReview'));
    }

    /**
     * Generate (or reuse) the AI review for a single student.
     */
    public function generate(Request $request, string $student_number): RedirectResponse
    {
        $student = User::query()
            ->where('student_number', $student_number)
            ->where('roles_id', 2)
            ->firstOrFail();

        $screening = $this->screening->screenStudent($student);

        // Reuse an existing saved review instead of hitting the API again.
        $saved = AIGraduationReview::latestFor($student_number);
        if ($saved && $saved->ai_generated_at?->isToday()) {
            return redirect()
                ->route('admin.graduation-review.show', $student_number)
                ->with('info', 'The AI review was generated earlier today and has been reused to avoid extra API calls.');
        }

        $adminId = Auth::guard('admin')->id();

        if (! $this->ai->isAvailable()) {
            return redirect()
                ->route('admin.graduation-review.show', $student_number)
                ->withErrors([
                    'ai' => 'AI analysis is temporarily unavailable. The factual disciplinary screening is still available for manual review.',
                ]);
        }

        try {
            $result = $this->ai->generate($screening);
        } catch (\Throwable $e) {
            Log::warning('AI graduation review generation failed.', [
                'student_number' => $student_number,
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->route('admin.graduation-review.show', $student_number)
                ->withErrors([
                    'ai' => 'AI analysis is temporarily unavailable. The factual disciplinary screening is still available for manual review.',
                ]);
        }

        $review = AIGraduationReview::create([
            'student_number' => $student_number,
            'generated_by_admin_id' => $adminId,
            'review_status' => $result['review_status'],
            'summary' => $result['summary'],
            'disciplinary_pattern' => $result['disciplinary_pattern'],
            'reason_for_review' => $result['reason_for_review'],
            'recommended_action' => $result['recommended_action'],
            'outstanding_sanctions' => $result['outstanding_sanctions'],
            'total_violations' => $screening['total_violations'],
            'completed_sanctions' => $screening['completed_sanctions'],
            'pending_sanctions' => $screening['pending_sanctions'],
            'outstanding_sanctions_count' => $screening['outstanding_sanctions'],
            'ai_model' => (string) config('graduation_review.ai.model'),
            'ai_generated_at' => now(),
        ]);

        return redirect()
            ->route('admin.graduation-review.show', $student_number)
            ->with('success', 'AI review generated successfully.');
    }

    /**
     * Generate AI reviews for the whole cohort in a controlled batch.
     */
    public function generateBatch(Request $request): RedirectResponse
    {
        $students = $this->screening->graduatingStudentsQuery()
            ->orderBy('last_name')
            ->get();

        $limit = (int) config('graduation_review.batch_limit', 25);

        if ($limit > 0) {
            $students = $students->take($limit);
        }

        if (! $this->ai->isAvailable()) {
            return redirect()
                ->route('admin.graduation-review.index')
                ->withErrors([
                    'ai' => 'AI analysis is temporarily unavailable. The factual disciplinary screening is still available for manual review.',
                ]);
        }

        $adminId = Auth::guard('admin')->id();
        $model = (string) config('graduation_review.ai.model');
        $generated = 0;
        $reused = 0;
        $failed = 0;

        foreach ($students as $student) {
            $studentNumber = $student->student_number;

            $saved = AIGraduationReview::latestFor($studentNumber);
            if ($saved && $saved->ai_generated_at?->isToday()) {
                $reused++;
                continue;
            }

            try {
                $screening = $this->screening->screenStudent($student);
                $result = $this->ai->generate($screening);

                AIGraduationReview::create([
                    'student_number' => $studentNumber,
                    'generated_by_admin_id' => $adminId,
                    'review_status' => $result['review_status'],
                    'summary' => $result['summary'],
                    'disciplinary_pattern' => $result['disciplinary_pattern'],
                    'reason_for_review' => $result['reason_for_review'],
                    'recommended_action' => $result['recommended_action'],
                    'outstanding_sanctions' => $result['outstanding_sanctions'],
                    'total_violations' => $screening['total_violations'],
                    'completed_sanctions' => $screening['completed_sanctions'],
                    'pending_sanctions' => $screening['pending_sanctions'],
                    'outstanding_sanctions_count' => $screening['outstanding_sanctions'],
                    'ai_model' => $model,
                    'ai_generated_at' => now(),
                ]);

                $generated++;
            } catch (\Throwable $e) {
                Log::warning('Batch AI graduation review failed for a student.', [
                    'student_number' => $studentNumber,
                    'error' => $e->getMessage(),
                ]);

                $failed++;
            }
        }

        $message = "Batch AI review complete: {$generated} generated, {$reused} reused (from today), {$failed} failed.";

        if ($failed > 0) {
            return redirect()
                ->route('admin.graduation-review.index')
                ->with('success', $message)
                ->withErrors(['ai' => 'Some AI analyses were unavailable. The factual disciplinary screening remains available for manual review.']);
        }

        return redirect()
            ->route('admin.graduation-review.index')
            ->with('success', $message);
    }

    /**
     * Build the consolidated report counts from screened students.
     */
    protected function buildReport($screened): array
    {
        $noHistory = $screened->filter(fn ($s) => (int) $s['total_violations'] === 0)->count();
        $allCompleted = $screened->filter(
            fn ($s) => (int) $s['total_violations'] > 0 && (int) $s['outstanding_sanctions'] === 0
        )->count();
        $outstanding = $screened->filter(fn ($s) => (int) $s['outstanding_sanctions'] > 0)->count();
        $manual = $screened->filter(fn ($s) => (bool) $s['repeated_violations_exist'] && (int) $s['outstanding_sanctions'] === 0)->count();

        return [
            'total' => $screened->count(),
            'no_history' => $noHistory,
            'all_completed' => $allCompleted,
            'outstanding' => $outstanding,
            'manual_review' => $manual,
        ];
    }
}
