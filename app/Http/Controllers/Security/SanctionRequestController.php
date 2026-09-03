<?php

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\DisciplinarySanction;
use App\Models\Notification;
use App\Models\SanctionRequest;
use App\Models\StudentSanctionRecord;
use App\Models\Violation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SanctionRequestController extends Controller
{
    public function index()
    {
        $requests = SanctionRequest::with(['student', 'violationType'])
            ->where('is_active', true)
            ->latest('request_id')
            ->paginate(15);

        return view('security.sanction-requests.index', compact('requests'));
    }

    public function approve(SanctionRequest $sanctionRequest): RedirectResponse
    {
        $this->finalizeRequest($sanctionRequest, 'approved');

        return back()->with('success', 'Sanction request approved and forwarded for follow-up.');
    }

    public function decline(SanctionRequest $sanctionRequest): RedirectResponse
    {
        $this->finalizeRequest($sanctionRequest, 'declined');

        return back()->with('success', 'Sanction request declined and the student has been notified.');
    }

    private function finalizeRequest(SanctionRequest $sanctionRequest, string $decision): void
    {
        DB::transaction(function () use ($sanctionRequest, $decision): void {
            $security = Auth::guard('security')->user();
            $securityId = $security ? (int) $security->id : null;

            $sanctionRequest->update(['is_active' => false]);

            $student = $sanctionRequest->student;
            $violationType = $sanctionRequest->violationType;

            if ($student && $violationType) {
                $latestViolation = Violation::where('student_number', $student->student_number)
                    ->where('violation_type', $violationType->violation_type_id)
                    ->effective()
                    ->latest('violation_date')
                    ->first();

                $offenseCount = Violation::where('student_number', $student->student_number)
                    ->where('violation_type', $violationType->violation_type_id)
                    ->effective()
                    ->count();

                $offenseLevel = $this->formatOffenseLevel($offenseCount);

                $assignedSanction = DisciplinarySanction::where('violation_type_id', $violationType->violation_type_id)
                    ->where('offense_level', $offenseLevel)
                    ->first()
                    ?? DisciplinarySanction::where('violation_type_id', $violationType->violation_type_id)
                    ->orderBy('disciplinary_sanction_id')
                    ->first();

                $sanctionText = strtolower((string) ($assignedSanction?->disciplinary_sanction ?? ''));
                if ($decision === 'approved' && $sanctionText !== '' && str_contains($sanctionText, 'warning')) {
                    throw ValidationException::withMessages([
                        'sanction_request' => 'Warning-level violations should remain in the individual violations log and cannot be moved to sanction records.',
                    ]);
                }

                if ($decision === 'approved' && $latestViolation) {
                    $pendingRecord = StudentSanctionRecord::where('student_number', $student->student_number)
                        ->where('violation_id', $latestViolation->violation_id)
                        ->where(function ($q) {
                            $q->where('status', \App\Models\StudentSanctionRecord::STATUS_PENDING)
                                ->orWhere('status', \App\Models\StudentSanctionRecord::STATUS_IN_PROGRESS)
                                ->orWhere('status', \App\Models\StudentSanctionRecord::STATUS_OVERDUE);
                        })
                        ->latest('record_id')
                        ->first();

                    if (! $pendingRecord) {
                        StudentSanctionRecord::create([
                            'student_number' => $student->student_number,
                            'violation_id' => $latestViolation->violation_id,
                            'assigned_sanction_id' => $assignedSanction?->disciplinary_sanction_id,
                            // The assigned_by_admin_id is only set by an actual
                            // admin approval. A security-mediated approval does
                            // not fabricate an admin id — it stays null until an
                            // administrator assigns the sanction with a deadline.
                            'assigned_by_admin_id' => null,
                            'status' => \App\Models\StudentSanctionRecord::STATUS_IN_PROGRESS,
                            'date_assigned' => now(),
                        ]);
                    }
                }
            }

            if ($student?->student_number) {
                try {
                    app(\App\Services\StudentNotificationService::class)->create(
                        studentNumber: $student->student_number,
                        type: 'sanction',
                        title: $decision === 'approved'
                            ? 'Sanction Request Approved'
                            : 'Sanction Request Declined',
                        message: $decision === 'approved'
                            ? 'Your sanction request was approved by the security office.'
                            : 'Your sanction request was declined by the security office.',
                        targetType: 'sanction_request',
                        targetId: (int) $sanctionRequest->request_id,
                    );
                } catch (\Throwable $e) {
                    // Notification must never break the decision.
                }
            }

            AuditLog::create([
                'actor_type' => 'security',
                'actor_id' => $securityId,
                'action' => ucfirst($decision),
                'module' => 'Sanction Requests',
                'description' => 'Security processed sanction request #' . $sanctionRequest->request_id . ' for student ' . ($student?->student_number ?? 'unknown'),
            ]);
        });
    }

    private function formatOffenseLevel(int $offenseCount): string
    {
        return match ($offenseCount) {
            1 => '1st Offense',
            2 => '2nd Offense',
            3 => '3rd Offense',
            default => $offenseCount . 'th Offense',
        };
    }
}
