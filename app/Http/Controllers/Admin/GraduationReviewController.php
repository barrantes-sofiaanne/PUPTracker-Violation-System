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
        return view('admin.graduation-review.index', $this->reviewData($request));
    }

    /**
     * Data for the graduation AI review view.
     *
     * Extracted from index() unchanged so the SAME dataset feeds both the
     * standalone page and the "Graduation AI Report" tab of Admin → Reports.
     * Having one method means the two entry points cannot drift apart.
     *
     * @return array{programs: \Illuminate\Database\Eloquent\Collection, years: \Illuminate\Database\Eloquent\Collection, programFilter: int, yearFilter: int, screened: \Illuminate\Support\Collection, report: array, students: \Illuminate\Support\Collection}
     */
    public function reviewData(Request $request): array
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

        return compact(
            'programs',
            'years',
            'programFilter',
            'yearFilter',
            'screened',
            'report',
            'students',
        );
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
     * Generate AI reviews for the whole cohort via the queue.
     *
     * One job per student is dispatched into a job batch so 1,000+ students can
     * be processed independently without holding the browser open.
     */
    public function generateBatch(Request $request): RedirectResponse
    {
        if (! $this->ai->isAvailable()) {
            return redirect()
                ->route('admin.graduation-review.index')
                ->withErrors([
                    'ai' => 'AI analysis is temporarily unavailable. The factual disciplinary screening is still available for manual review.',
                ]);
        }

        $adminId = Auth::guard('admin')->id();

        // Collect the student numbers we still need to review (respects the
        // fingerprint/change-detection so we don't regenerate unchanged records).
        $studentNumbers = $this->studentsNeedingReview();

        if ($studentNumbers->isEmpty()) {
            return redirect()
                ->route('admin.graduation-review.index')
                ->with('info', 'No students need a new AI review. Existing reviews are still current.');
        }

        $batch = \Illuminate\Support\Facades\Bus::batch(
            $studentNumbers
                ->map(fn (string $number) => new \App\Jobs\GenerateStudentAIReview($number, $adminId))
                ->all()
        )
            ->name('graduation-review-batch')
            ->onConnection(config('graduation_review.queue.connection', 'database'))
            ->onQueue(config('graduation_review.queue.name', 'graduation-review'))
            ->dispatch();

        session(['graduation_review.batch_id' => $batch->id]);

        return redirect()
            ->route('admin.graduation-review.index')
            ->with('success', "AI review jobs dispatched for {$studentNumbers->count()} student(s). Progress is shown below.");
    }

    /**
     * Retrieve the current batch progress as JSON for the progress bar.
     */
    public function batchProgress(Request $request): \Illuminate\Http\JsonResponse
    {
        $batchId = (string) $request->string('batch_id', session('graduation_review.batch_id', ''));

        if ($batchId === '') {
            return response()->json(['available' => false]);
        }

        $batch = \Illuminate\Support\Facades\Bus::findBatch($batchId);

        if (! $batch) {
            return response()->json(['available' => false]);
        }

        $total = (int) $batch->totalJobs;
        $pending = (int) $batch->pendingJobs;
        $failed = (int) $batch->failedJobs;
        $processed = $total - $pending;
        $percent = $total > 0 ? (int) round(($processed / $total) * 100) : 0;

        return response()->json([
            'available' => true,
            'batch_id' => $batch->id,
            'total' => $total,
            'pending' => $pending,
            'processed' => $processed,
            'failed' => $failed,
            'remaining' => $pending,
            'percent' => $percent,
            'finished' => $batch->finished(),
        ]);
    }

    /**
     * Re-dispatch only failed/outdated reviews (never regenerates successes).
     */
    public function retryFailed(Request $request): RedirectResponse
    {
        $adminId = Auth::guard('admin')->id();

        // Students whose review is outdated (records changed) OR that have no
        // fresh review are candidates for retry.
        $numbers = \App\Models\AIGraduationReview::query()
            ->where(function ($q) {
                $q->whereNull('data_fingerprint')
                    ->orWhere('is_outdated', true);
            })
            ->where('student_number', '!=', '')
            ->pluck('student_number')
            ->unique()
            ->values();

        if ($numbers->isEmpty()) {
            return redirect()
                ->route('admin.graduation-review.index')
                ->with('info', 'No failed reviews to retry.');
        }

        $batch = \Illuminate\Support\Facades\Bus::batch(
            $numbers
                ->map(fn (string $number) => new \App\Jobs\GenerateStudentAIReview($number, $adminId))
                ->all()
        )
            ->name('graduation-review-retry')
            ->onConnection(config('graduation_review.queue.connection', 'database'))
            ->onQueue(config('graduation_review.queue.name', 'graduation-review'))
            ->dispatch();

        session(['graduation_review.batch_id' => $batch->id]);

        return redirect()
            ->route('admin.graduation-review.index')
            ->with('success', "Retry jobs dispatched for {$numbers->count()} student(s).");
    }

    /**
     * Record the human-in-the-loop decision for a student's AI review.
     *
     * The AI never makes the final decision — the authorized admin does.
     */
    public function storeDecision(Request $request, string $student_number): RedirectResponse
    {
        $review = AIGraduationReview::latestFor($student_number);

        if (! $review) {
            return redirect()
                ->route('admin.graduation-review.show', $student_number)
                ->withErrors(['decision' => 'No AI review exists for this student yet.']);
        }

        $validated = $request->validate([
            'decision_status' => ['required', 'in:agreed,modified,rejected'],
            'decision_remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        $review->update([
            'decision_status' => $validated['decision_status'],
            'decision_remarks' => trim((string) ($validated['decision_remarks'] ?? '')),
            'decision_by_admin_id' => Auth::guard('admin')->id(),
            'decision_at' => now(),
        ]);

        return redirect()
            ->route('admin.graduation-review.show', $student_number)
            ->with('success', 'Administrator decision recorded.');
    }

    /**
     * Student numbers that still need a review, based on the data fingerprint.
     */
    protected function studentsNeedingReview(): \Illuminate\Support\Collection
    {
        $students = $this->screening->graduatingStudentsQuery()
            ->orderBy('last_name')
            ->get(['student_number']);

        return $students
            ->filter(function (User $student) {
                $data = $this->screening->screenStudent($student);

                return AIGraduationReview::latestFreshFor(
                    $student->student_number,
                    $data['data_fingerprint'],
                ) === null;
            })
            ->pluck('student_number')
            ->values();
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
