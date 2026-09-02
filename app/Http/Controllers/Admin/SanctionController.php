<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\DisciplinarySanction;
use App\Models\Notification;
use App\Models\SanctionRequest;
use App\Models\StudentSanctionRecord;
use App\Models\Violation;
use App\Models\ViolationType;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SanctionController extends Controller
{
    public function index()
    {
        $sanctions = DisciplinarySanction::with('violationType.violationCategory')
            ->orderBy('violation_type_id')
            ->orderBy('offense_level')
            ->get();

        $violationTypes = ViolationType::with('violationCategory')
            ->orderBy('violation_type')
            ->get();

        $requests = SanctionRequest::with(['student', 'violationType.violationCategory'])
            ->where('is_active', true)
            ->latest('request_date')
            ->paginate(15, ['*'], 'requests_page')
            ->withQueryString();

        $studentSanctionsQuery = StudentSanctionRecord::with([
            'violation.violationType',
            'assignedSanction',
            'violation.student',
            'completionReviewer',
        ])->whereHas('violation.violationType');

        $recordSearch = trim((string) request('record_search', ''));
        if ($recordSearch !== '') {
            $studentSanctionsQuery->where(function ($query) use ($recordSearch) {
                $query->where('student_number', 'like', '%' . $recordSearch . '%')
                    ->orWhereHas('violation.violationType', function ($violationTypeQuery) use ($recordSearch) {
                        $violationTypeQuery->where('violation_type', 'like', '%' . $recordSearch . '%');
                    })
                    ->orWhereHas('violation.student', function ($studentQuery) use ($recordSearch) {
                        $studentQuery->where('first_name', 'like', '%' . $recordSearch . '%')
                            ->orWhere('last_name', 'like', '%' . $recordSearch . '%');
                    });
            });
        }

        $recordStatuses = request('record_statuses', []);
        if (!is_array($recordStatuses)) {
            $recordStatuses = [];
        }

        $normalizedStatuses = array_values(array_filter(array_map(static function ($status) {
            return trim((string) $status);
        }, $recordStatuses)));

        if ($normalizedStatuses !== []) {
            $studentSanctionsQuery->whereIn('status', $normalizedStatuses);
        } else {
            $recordStatus = trim((string) request('record_status', ''));
            if ($recordStatus !== '') {
                $studentSanctionsQuery->where('status', $recordStatus);
            }
        }

        $recordViolationTypeId = request('record_violation_type');
        if (!empty($recordViolationTypeId)) {
            $studentSanctionsQuery->whereHas('violation', function ($violationQuery) use ($recordViolationTypeId) {
                $violationQuery->where('violation_type', $recordViolationTypeId);
            });
        }

        $recordFromDate = request('record_from_date');
        if (!empty($recordFromDate)) {
            $studentSanctionsQuery->whereDate('date_assigned', '>=', $recordFromDate);
        }

        $recordToDate = request('record_to_date');
        if (!empty($recordToDate)) {
            $studentSanctionsQuery->whereDate('date_assigned', '<=', $recordToDate);
        }

        $studentSanctions = $studentSanctionsQuery
            ->latest('date_assigned')
            ->paginate(15, ['*'], 'records_page')
            ->withQueryString();

        $requests->getCollection()->transform(function (SanctionRequest $request) {
            $offenseCount = Violation::where('student_number', $request->student_number)
                ->where('violation_type', $request->violation_type_id)
                ->effective()
                ->count();

            $offenseLevel = $this->formatOffenseLevel($offenseCount);

            $latestViolation = Violation::where('student_number', $request->student_number)
                ->where('violation_type', $request->violation_type_id)
                ->effective()
                ->latest('violation_date')
                ->first();

            $assignedSanction = DisciplinarySanction::where('violation_type_id', $request->violation_type_id)
                ->where('offense_level', $offenseLevel)
                ->first()
                ?? DisciplinarySanction::where('violation_type_id', $request->violation_type_id)
                    ->orderBy('disciplinary_sanction_id')
                    ->first();

            $request->setAttribute('resolved_offense_level', $offenseLevel);
            $request->setAttribute('resolved_violation_description', $latestViolation?->description ?? 'No remarks recorded.');
            $request->setAttribute('resolved_sanction_description', $assignedSanction?->disciplinary_sanction ?? 'No matching sanction is configured yet.');

            return $request;
        });

        return view('admin.sanctions.index', compact('sanctions', 'violationTypes', 'requests', 'studentSanctions'));
    }

    public function showRequest(SanctionRequest $sanctionRequest)
    {
        $sanctionRequest->load(['student', 'violationType.violationCategory']);

        $latestViolation = Violation::where('student_number', $sanctionRequest->student_number)
            ->where('violation_type', $sanctionRequest->violation_type_id)
            ->effective()
            ->latest('violation_date')
            ->first();

        $offenseCount = Violation::where('student_number', $sanctionRequest->student_number)
            ->where('violation_type', $sanctionRequest->violation_type_id)
            ->effective()
            ->count();
        $offenseLevel = $this->formatOffenseLevel($offenseCount);
        $assignedSanction = DisciplinarySanction::where('violation_type_id', $sanctionRequest->violation_type_id)
            ->where('offense_level', $offenseLevel)
            ->first()
            ?? DisciplinarySanction::where('violation_type_id', $sanctionRequest->violation_type_id)
                ->orderBy('disciplinary_sanction_id')
                ->first();

        return view('admin.sanctions.request', compact(
            'sanctionRequest',
            'latestViolation',
            'offenseLevel',
            'assignedSanction'
        ));
    }

    public function approveRequest(Request $request, SanctionRequest $sanctionRequest): RedirectResponse
    {
        if (!$sanctionRequest->is_active) {
            return back()->withErrors([
                'sanction_request' => 'This sanction request has already been processed.',
            ]);
        }

        $validated = $request->validate([
            'notification_date' => ['required', 'date'],
            'deadline_date' => ['nullable', 'date', 'after_or_equal:notification_date'],
        ]);

        $adminId = Auth::guard('admin')->id();
        $scheduledDate = Carbon::parse($validated['notification_date'])->startOfDay();
        $deadlineDate = !empty($validated['deadline_date'])
            ? Carbon::parse($validated['deadline_date'])->endOfDay()
            : null;

        DB::transaction(function () use ($sanctionRequest, $adminId, $scheduledDate, $deadlineDate): void {
            $student = $sanctionRequest->student;
            $violationType = $sanctionRequest->violationType;

            if (!$student || !$violationType) {
                throw ValidationException::withMessages([
                    'sanction_request' => 'This request cannot be processed because student or violation details are missing.',
                ]);
            }

            $latestViolation = Violation::where('student_number', $student->student_number)
                ->where('violation_type', $violationType->violation_type_id)
                ->effective()
                ->latest('violation_date')
                ->first();

            if (!$latestViolation) {
                throw ValidationException::withMessages([
                    'sanction_request' => 'No matching violation record was found for this request.',
                ]);
            }

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
            if ($sanctionText !== '' && str_contains($sanctionText, 'warning')) {
                throw ValidationException::withMessages([
                    'sanction_request' => 'Warning-level violations should remain in the individual violations log and cannot be moved to sanction records.',
                ]);
            }

            $pendingRecord = StudentSanctionRecord::where('student_number', $student->student_number)
                ->where('violation_id', $latestViolation->violation_id)
                ->where('status', 'Pending')
                ->latest('record_id')
                ->first();

            if ($pendingRecord) {
                $pendingRecord->update([
                    'assigned_sanction_id' => $assignedSanction?->disciplinary_sanction_id,
                    'assigned_by_admin_id' => $adminId,
                    'date_assigned' => $scheduledDate,
                    'deadline_date' => $deadlineDate,
                ]);
            } else {
                StudentSanctionRecord::create([
                    'student_number' => $student->student_number,
                    'violation_id' => $latestViolation->violation_id,
                    'assigned_sanction_id' => $assignedSanction?->disciplinary_sanction_id,
                    'assigned_by_admin_id' => $adminId,
                    'status' => 'Pending',
                    'date_assigned' => $scheduledDate,
                    'deadline_date' => $deadlineDate,
                ]);
            }

            $sanctionRequest->update([
                'is_active' => false,
                'status' => 'Approved',
                'approved_by_admin_id' => $adminId,
                'approved_at' => now(),
            ]);

            try {
                $deadlineMessage = $deadlineDate
                    ? ' Deadline to complete: ' . $deadlineDate->format('M d, Y') . '.'
                    : '';
                app(\App\Services\StudentNotificationService::class)->create(
                    studentNumber: $student->student_number,
                    type: 'sanction',
                    title: 'Sanction Request Approved',
                    message: 'Your sanction request was approved. Please check your Sanction Record tab. Scheduled date: ' . $scheduledDate->format('M d, Y') . '.' . $deadlineMessage,
                    targetType: 'sanction_request',
                    targetId: (int) $sanctionRequest->request_id,
                );
            } catch (\Throwable $e) {
                // Notification must never break the approval.
            }

            AuditLog::create([
                'actor_type' => 'admin',
                'actor_id' => $adminId,
                'action' => 'Approve',
                'module' => 'Sanction Requests',
                'description' => 'Approved sanction request #' . $sanctionRequest->request_id . ' for student ' . $student->student_number . ($deadlineDate ? ' with deadline ' . $deadlineDate->format('Y-m-d') : '') . '.',
            ]);
        });

        return back()->with('success', 'Sanction request approved and added to the student sanction tab.');
    }

    public function declineRequest(SanctionRequest $sanctionRequest): RedirectResponse
    {
        if (!$sanctionRequest->is_active) {
            return back()->withErrors([
                'sanction_request' => 'This sanction request has already been processed.',
            ]);
        }

        $adminId = Auth::guard('admin')->id();

        DB::transaction(function () use ($sanctionRequest, $adminId): void {
            $student = $sanctionRequest->student;

            $sanctionRequest->update([
                'is_active' => false,
                'status' => 'Declined',
                'approved_by_admin_id' => $adminId,
                'approved_at' => now(),
            ]);

            if ($student) {
                try {
                    app(\App\Services\StudentNotificationService::class)->create(
                        studentNumber: $student->student_number,
                        type: 'sanction',
                        title: 'Sanction Request Declined',
                        message: 'Your sanction request was declined by the administration.',
                        targetType: 'sanction_request',
                        targetId: (int) $sanctionRequest->request_id,
                    );
                } catch (\Throwable $e) {
                    // Notification must never break the decline.
                }
            }

            AuditLog::create([
                'actor_type' => 'admin',
                'actor_id' => $adminId,
                'action' => 'Decline',
                'module' => 'Sanction Requests',
                'description' => 'Declined sanction request #' . $sanctionRequest->request_id . '.',
            ]);
        });

        return back()->with('success', 'Sanction request declined and the student was notified.');
    }

    public function acceptProof(StudentSanctionRecord $studentSanctionRecord): RedirectResponse
    {
        if (strcasecmp((string) $studentSanctionRecord->status, 'Pending') !== 0) {
            return back()->withErrors([
                'sanction_record' => 'Only pending sanctions with a submitted proof can be completed.',
            ]);
        }

        if ($studentSanctionRecord->proof_path === null || $studentSanctionRecord->proof_path === '') {
            return back()->withErrors([
                'sanction_record' => 'This sanction has no submitted proof of completion to accept.',
            ]);
        }

        $adminId = Auth::guard('admin')->id();

        DB::transaction(function () use ($studentSanctionRecord, $adminId): void {
            $studentSanctionRecord->update([
                'status' => 'Completed',
                'date_completed' => now(),
                'completion_reviewed_by_admin_id' => $adminId,
                'completion_reviewed_at' => now(),
            ]);

            try {
                app(\App\Services\StudentNotificationService::class)->create(
                    studentNumber: $studentSanctionRecord->student_number,
                    type: 'sanction',
                    title: 'Sanction Completed',
                    message: 'Your submitted proof of completion was accepted. Your sanction is now marked as completed.',
                    targetType: 'sanction_request',
                    targetId: (int) $studentSanctionRecord->record_id,
                );
            } catch (\Throwable $e) {
                // Notification must never break the completion.
            }

            AuditLog::create([
                'actor_type' => 'admin',
                'actor_id' => $adminId,
                'action' => 'Accept Proof',
                'module' => 'Sanction Records',
                'description' => 'Accepted proof of completion and marked sanction record #' . $studentSanctionRecord->record_id . ' as completed.',
            ]);
        });

        return back()->with('success', 'Proof accepted and sanction marked as completed.');
    }

    public function rejectProof(Request $request, StudentSanctionRecord $studentSanctionRecord): RedirectResponse
    {
        if (strcasecmp((string) $studentSanctionRecord->status, 'Pending') !== 0) {
            return back()->withErrors([
                'sanction_record' => 'Only pending sanctions with a submitted proof can be rejected.',
            ]);
        }

        if ($studentSanctionRecord->proof_path === null || $studentSanctionRecord->proof_path === '') {
            return back()->withErrors([
                'sanction_record' => 'This sanction has no submitted proof of completion to reject.',
            ]);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $adminId = Auth::guard('admin')->id();

        DB::transaction(function () use ($studentSanctionRecord, $adminId, $validated): void {
            $studentSanctionRecord->update([
                'status' => 'Pending',
                'completion_reviewed_by_admin_id' => $adminId,
                'completion_reviewed_at' => now(),
                'completion_remarks' => trim($validated['reason']),
            ]);

            try {
                app(\App\Services\StudentNotificationService::class)->create(
                    studentNumber: $studentSanctionRecord->student_number,
                    type: 'sanction',
                    title: 'Proof of Completion Rejected',
                    message: 'Your proof of completion was not accepted. Reason: ' . trim($validated['reason']) . '. You may submit a new proof for verification.',
                    targetType: 'sanction_request',
                    targetId: (int) $studentSanctionRecord->record_id,
                );
            } catch (\Throwable $e) {
                // Notification must never break the rejection.
            }

            AuditLog::create([
                'actor_type' => 'admin',
                'actor_id' => $adminId,
                'action' => 'Reject Proof',
                'module' => 'Sanction Records',
                'description' => 'Rejected proof of completion for sanction record #' . $studentSanctionRecord->record_id . '. Reason: ' . trim($validated['reason']) . '.',
            ]);
        });

        return back()->with('success', 'Proof rejected. The student was notified and may resubmit.');
    }

    public function revertRecordToPending(StudentSanctionRecord $studentSanctionRecord): RedirectResponse
    {
        if (strcasecmp((string) $studentSanctionRecord->status, 'Completed') !== 0) {
            return back()->withErrors([
                'sanction_record' => 'Only completed sanctions can be reverted to pending.',
            ]);
        }

        $adminId = Auth::guard('admin')->id();

        DB::transaction(function () use ($studentSanctionRecord, $adminId): void {
            $studentSanctionRecord->update([
                'status' => 'Pending',
                'date_completed' => null,
                // Reverting reopens the proof workflow: clear the review outcome
                // so the student can submit a fresh proof for verification.
                'completion_reviewed_by_admin_id' => null,
                'completion_reviewed_at' => null,
                'completion_remarks' => null,
                'proof_path' => null,
                'proof_original_name' => null,
                'proof_submitted_at' => null,
                'proof_notes' => null,
            ]);

            try {
                app(\App\Services\StudentNotificationService::class)->create(
                    studentNumber: $studentSanctionRecord->student_number,
                    type: 'sanction',
                    title: 'Sanction Reverted',
                    message: 'Your sanction status was reverted to pending by the administration. You may submit a new proof of completion for verification.',
                    targetType: 'sanction_request',
                    targetId: (int) $studentSanctionRecord->record_id,
                );
            } catch (\Throwable $e) {
                // Notification must never break the revert.
            }

            AuditLog::create([
                'actor_type' => 'admin',
                'actor_id' => $adminId,
                'action' => 'Revert',
                'module' => 'Sanction Records',
                'description' => 'Reverted sanction record #' . $studentSanctionRecord->record_id . ' back to pending.',
            ]);
        });

        return back()->with('success', 'Completed sanction reverted to pending.');
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
