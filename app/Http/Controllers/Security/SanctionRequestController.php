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
use Illuminate\Support\Facades\DB;

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
            $sanctionRequest->update(['is_active' => false]);

            $student = $sanctionRequest->student;
            $violationType = $sanctionRequest->violationType;

            if ($student && $violationType) {
                $latestViolation = Violation::where('student_number', $student->student_number)
                    ->where('violation_type', $violationType->violation_type_id)
                    ->latest('violation_date')
                    ->first();

                $assignedSanction = DisciplinarySanction::where('violation_type_id', $violationType->violation_type_id)
                    ->orderBy('disciplinary_sanction_id')
                    ->first();

                if ($latestViolation) {
                    StudentSanctionRecord::create([
                        'student_number' => $student->student_number,
                        'violation_id' => $latestViolation->violation_id,
                        'assigned_sanction_id' => $assignedSanction?->disciplinary_sanction_id,
                        'status' => 'Pending',
                        'date_assigned' => now(),
                    ]);
                }
            }

            Notification::create([
                'student_number' => $student?->student_number,
                'message' => $decision === 'approved'
                    ? 'Your sanction request was approved by the security office.'
                    : 'Your sanction request was declined by the security office.',
                'is_read' => false,
                'link' => route('student.record'),
                'notification_type' => 'sanction',
                'recipient_type' => 'student',
            ]);

            AuditLog::create([
                'actor_type' => 'security',
                'actor_id' => 1,
                'action' => ucfirst($decision),
                'module' => 'Sanction Requests',
                'description' => 'Security processed sanction request #' . $sanctionRequest->request_id . ' for student ' . ($student?->student_number ?? 'unknown'),
            ]);
        });
    }
}
