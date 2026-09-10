<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\DisciplinarySanction;
use App\Models\Notification;
use App\Models\SanctionProof;
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
        // Flip any In Progress/Pending sanctions past their deadline to Overdue
        // (fires one-time notifications) before listing.
        try {
            app(\App\Services\SanctionOverdueService::class)->sweep();
        } catch (\Throwable $e) {
            // Sweep failures must never block the sanctions page.
        }

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

        $suggestedDeadline = $this->deadlineFromSanctionText(
            $assignedSanction?->disciplinary_sanction,
            now()->startOfDay()
        );

        $isServiceSanction = $this->isServiceSanctionText($assignedSanction?->disciplinary_sanction);

        return view('admin.sanctions.request', compact(
            'sanctionRequest',
            'latestViolation',
            'offenseLevel',
            'assignedSanction',
            'suggestedDeadline',
            'isServiceSanction'
        ));
    }

    public function approveRequest(Request $request, SanctionRequest $sanctionRequest): RedirectResponse
    {
        if (!$sanctionRequest->is_active) {
            return back()->withErrors([
                'sanction_request' => 'This sanction request has already been processed.',
            ]);
        }

        // Resolve the sanction that will be assigned so we can decide whether
        // the Community Service / Campus Service assignment fields are needed.
        // The office + starting date are REQUIRED only for those two types;
        // every other sanction type keeps its existing workflow untouched.
        $resolvedSanction = $this->resolveAssignedSanction(
            (string) $sanctionRequest->student_number,
            (int) $sanctionRequest->violation_type_id
        );
        $isServiceSanction = $this->isServiceSanctionText($resolvedSanction?->disciplinary_sanction);

        $validated = $request->validate([
            'notification_date' => ['required', 'date'],
            'deadline_date' => ['nullable', 'date', 'after_or_equal:notification_date'],
            // Required ONLY for Community Service / Campus Service. For every
            // other sanction type these are optional and ignored.
            'designated_office' => array_filter([
                $isServiceSanction ? 'required' : 'nullable',
                'string',
                'max:255',
            ]),
            'starting_date' => array_filter([
                $isServiceSanction ? 'required' : 'nullable',
                'date',
            ]),
        ]);

        $adminId = Auth::guard('admin')->id();
        $scheduledDate = Carbon::parse($validated['notification_date'])->startOfDay();
        $deadlineDate = !empty($validated['deadline_date'])
            ? Carbon::parse($validated['deadline_date'])->endOfDay()
            : null;

        // Assignment fields are only meaningful for service sanctions. For any
        // other type they are stored as null so a record can never carry a
        // stale office/date from a previous assignment.
        $designatedOffice = ($isServiceSanction && !empty($validated['designated_office']))
            ? trim((string) $validated['designated_office'])
            : null;
        $startingDate = ($isServiceSanction && !empty($validated['starting_date']))
            ? Carbon::parse($validated['starting_date'])->startOfDay()
            : null;

        DB::transaction(function () use ($sanctionRequest, $adminId, $scheduledDate, &$deadlineDate, $designatedOffice, $startingDate): void {
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

            // A manually entered deadline always wins. Otherwise derive one
            // from durations stated in the sanction (e.g. "one month
            // suspension" or "one week community service").
            $deadlineDate ??= $this->deadlineFromSanctionText(
                $assignedSanction?->disciplinary_sanction,
                $scheduledDate
            );

            $pendingRecord = StudentSanctionRecord::where('student_number', $student->student_number)
                ->where('violation_id', $latestViolation->violation_id)
                ->where(function ($q) {
                    $q->where('status', StudentSanctionRecord::STATUS_PENDING)
                        ->orWhere('status', StudentSanctionRecord::STATUS_IN_PROGRESS)
                        ->orWhere('status', StudentSanctionRecord::STATUS_OVERDUE);
                })
                ->latest('record_id')
                ->first();

            if ($pendingRecord) {
                $pendingRecord->update([
                    'assigned_sanction_id' => $assignedSanction?->disciplinary_sanction_id,
                    'assigned_by_admin_id' => $adminId,
                    'date_assigned' => $scheduledDate,
                    'deadline_date' => $deadlineDate,
                    // Service assignment (null for non-service sanctions).
                    'designated_office' => $designatedOffice,
                    'starting_date' => $startingDate,
                    // Approving assigns the sanction — it is now in progress.
                    'status' => StudentSanctionRecord::STATUS_IN_PROGRESS,
                ]);
            } else {
                StudentSanctionRecord::create([
                    'student_number' => $student->student_number,
                    'violation_id' => $latestViolation->violation_id,
                    'assigned_sanction_id' => $assignedSanction?->disciplinary_sanction_id,
                    'assigned_by_admin_id' => $adminId,
                    'status' => StudentSanctionRecord::STATUS_IN_PROGRESS,
                    'date_assigned' => $scheduledDate,
                    'deadline_date' => $deadlineDate,
                    // Service assignment (null for non-service sanctions).
                    'designated_office' => $designatedOffice,
                    'starting_date' => $startingDate,
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
                $serviceMessage = $designatedOffice
                    ? ' Designated office: ' . $designatedOffice . '.'
                        . ($startingDate ? ' Starting date: ' . $startingDate->format('M d, Y') . '.' : '')
                    : '';
                app(\App\Services\StudentNotificationService::class)->create(
                    studentNumber: $student->student_number,
                    type: 'sanction',
                    title: 'Sanction Request Approved',
                    message: 'Your sanction request was approved. Please check your Sanction Record tab. Scheduled date: ' . $scheduledDate->format('M d, Y') . '.' . $serviceMessage . $deadlineMessage,
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
                'description' => 'Approved sanction request #' . $sanctionRequest->request_id . ' for student ' . $student->student_number
                    . ($designatedOffice ? ' with designated office "' . $designatedOffice . '"' : '')
                    . ($startingDate ? ' starting ' . $startingDate->format('Y-m-d') : '')
                    . ($deadlineDate ? ' with deadline ' . $deadlineDate->format('Y-m-d') : '') . '.',
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
        $studentSanctionRecord->loadMissing('assignedSanction');

        if (! $studentSanctionRecord->is_open) {
            return back()->withErrors([
                'sanction_record' => 'Only open sanctions with a submitted proof can be completed.',
            ]);
        }

        // Non-service sanctions take NO proof of completion: the student is
        // directed to the Office of Student Services for a personal
        // consultation, and the administrator closes the record once that
        // consultation has taken place.
        $requiresProof = $studentSanctionRecord->requires_proof_of_completion;

        if ($requiresProof && ($studentSanctionRecord->proof_path === null || $studentSanctionRecord->proof_path === '')) {
            return back()->withErrors([
                'sanction_record' => 'This sanction has no submitted proof of completion to accept.',
            ]);
        }

        $adminId = Auth::guard('admin')->id();

        DB::transaction(function () use ($studentSanctionRecord, $adminId, $requiresProof): void {
            $studentSanctionRecord->update([
                'status' => StudentSanctionRecord::STATUS_COMPLETED,
                'date_completed' => now(),
                'completion_reviewed_by_admin_id' => $adminId,
                'completion_reviewed_at' => now(),
                'completion_remarks' => null,
            ]);

            // Mark the latest submitted proof as accepted in history. Only
            // service sanctions have a proof to review.
            if ($requiresProof) {
                $this->markLatestProofReviewed($studentSanctionRecord, $adminId, 'accepted', null);
            }

            try {
                app(\App\Services\StudentNotificationService::class)->create(
                    studentNumber: $studentSanctionRecord->student_number,
                    type: 'sanction',
                    title: 'Sanction Completed',
                    message: $requiresProof
                        ? 'Your submitted proof of completion was accepted. Your sanction is now marked as completed.'
                        : 'Your disciplinary sanction has been marked as completed after your consultation at the Office of Student Services.',
                    targetType: 'sanction_request',
                    targetId: (int) $studentSanctionRecord->record_id,
                );
            } catch (\Throwable $e) {
                // Notification must never break the completion.
            }

            AuditLog::create([
                'actor_type' => 'admin',
                'actor_id' => $adminId,
                'action' => $requiresProof ? 'Accept Proof' : 'Mark Completed',
                'module' => 'Sanction Records',
                'description' => ($requiresProof
                        ? 'Accepted proof of completion and marked'
                        : 'Marked')
                    . ' sanction record #' . $studentSanctionRecord->record_id . ' as completed.',
            ]);
        });

        return back()->with('success', $requiresProof
            ? 'Proof accepted and sanction marked as completed.'
            : 'Sanction marked as completed.');
    }

    public function rejectProof(Request $request, StudentSanctionRecord $studentSanctionRecord): RedirectResponse
    {
        if (! $studentSanctionRecord->is_open) {
            return back()->withErrors([
                'sanction_record' => 'Only open sanctions with a submitted proof can be rejected.',
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
            // Rejecting a proof keeps the sanction assigned/open; it returns to
            // In Progress (if it was In Progress/Overdue) so the student can
            // resubmit. If it was still Pending (never assigned), keep Pending.
            $revertTo = strcasecmp((string) $studentSanctionRecord->status, StudentSanctionRecord::STATUS_PENDING) === 0
                ? StudentSanctionRecord::STATUS_PENDING
                : StudentSanctionRecord::STATUS_IN_PROGRESS;

            // Clear the "current proof" pointers so BOTH the admin and student
            // views stop treating the old (now rejected) file as pending review.
            // The rejected file's metadata + review outcome are preserved in the
            // sanction_proofs_tbl history rows, so nothing is lost. Clearing
            // proof_path also makes has_pending_proof() false again, which is
            // what lets the student resubmit a new proof from the student side
            // and lets the admin side show the record as awaiting a new proof.
            $studentSanctionRecord->update([
                'status' => $revertTo,
                'proof_path' => null,
                'proof_original_name' => null,
                'proof_submitted_at' => null,
                'proof_notes' => null,
                'completion_reviewed_by_admin_id' => $adminId,
                'completion_reviewed_at' => now(),
                'completion_remarks' => trim($validated['reason']),
            ]);

            // Mark the latest submitted proof as rejected in history.
            $this->markLatestProofReviewed($studentSanctionRecord, $adminId, 'rejected', trim($validated['reason']));

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
                'sanction_record' => 'Only completed sanctions can be reopened.',
            ]);
        }

        $adminId = Auth::guard('admin')->id();

        DB::transaction(function () use ($studentSanctionRecord, $adminId): void {
            // Reverting a completed sanction reopens it as In Progress (it was
            // already assigned). The proof history rows are retained.
            $studentSanctionRecord->update([
                'status' => StudentSanctionRecord::STATUS_IN_PROGRESS,
                'date_completed' => null,
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
                    title: 'Sanction Reopened',
                    message: 'Your completed sanction was reopened by the administration. You may submit a new proof of completion for verification.',
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
                'description' => 'Reopened sanction record #' . $studentSanctionRecord->record_id . ' as in progress.',
            ]);
        });

        return back()->with('success', 'Completed sanction reopened and set back to in progress.');
    }

    /**
     * Correct the designated office and/or starting date of a Community
     * Service / Campus Service assignment before completion. Authorized to
     * administrators only (nested inside the admin route group). The change is
     * recorded in the existing audit log. Students can never call this.
     */
    public function updateServiceAssignment(Request $request, StudentSanctionRecord $studentSanctionRecord): RedirectResponse
    {
        $studentSanctionRecord->loadMissing('assignedSanction');

        if (! $studentSanctionRecord->is_service_sanction) {
            return back()->withErrors([
                'sanction_record' => 'Only Community Service or Campus Service assignments have a designated office and starting date.',
            ]);
        }

        if (! $studentSanctionRecord->is_open) {
            return back()->withErrors([
                'sanction_record' => 'A completed sanction assignment can no longer be edited.',
            ]);
        }

        $validated = $request->validate([
            'designated_office' => ['required', 'string', 'max:255'],
            'starting_date' => ['required', 'date'],
        ]);

        $adminId = Auth::guard('admin')->id();
        $newOffice = trim((string) $validated['designated_office']);
        $newStart = Carbon::parse($validated['starting_date'])->startOfDay();

        $oldOffice = (string) $studentSanctionRecord->designated_office;
        $oldStart = optional($studentSanctionRecord->starting_date)->toDateString();

        DB::transaction(function () use ($studentSanctionRecord, $adminId, $newOffice, $newStart, $oldOffice, $oldStart): void {
            $studentSanctionRecord->update([
                'designated_office' => $newOffice,
                'starting_date' => $newStart,
            ]);

            try {
                app(\App\Services\StudentNotificationService::class)->create(
                    studentNumber: $studentSanctionRecord->student_number,
                    type: 'sanction',
                    title: 'Service Assignment Updated',
                    message: 'Your Community/Campus Service assignment was updated. Designated office: ' . $newOffice . '. Starting date: ' . $newStart->format('M d, Y') . '.',
                    targetType: 'sanction_request',
                    targetId: (int) $studentSanctionRecord->record_id,
                );
            } catch (\Throwable $e) {
                // Notification must never break the update.
            }

            AuditLog::create([
                'actor_type' => 'admin',
                'actor_id' => $adminId,
                'action' => 'Update Service Assignment',
                'module' => 'Sanction Records',
                'description' => 'Updated service assignment for sanction record #' . $studentSanctionRecord->record_id
                    . ': office "' . $oldOffice . '" -> "' . $newOffice . '"'
                    . ', starting date ' . ($oldStart ?: 'none') . ' -> ' . $newStart->toDateString() . '.',
            ]);
        });

        return back()->with('success', 'Service assignment updated.');
    }

    /**
     * Whether a sanction description text is a Community Service or Campus
     * Service sanction (the only types that carry a designated office and a
     * starting date).
     */
    private function isServiceSanctionText(?string $sanctionText): bool
    {
        $text = strtolower(trim((string) $sanctionText));

        return $text !== ''
            && (str_contains($text, 'community') || str_contains($text, 'campus service'));
    }

    /**
     * Resolve the DisciplinarySanction that approveRequest() will assign for a
     * student's violation type, matching the same offense-level fallback used
     * when the record is actually created.
     */
    private function resolveAssignedSanction(string $studentNumber, int $violationTypeId): ?DisciplinarySanction
    {
        $offenseCount = Violation::where('student_number', $studentNumber)
            ->where('violation_type', $violationTypeId)
            ->effective()
            ->count();

        $offenseLevel = $this->formatOffenseLevel($offenseCount);

        return DisciplinarySanction::where('violation_type_id', $violationTypeId)
            ->where('offense_level', $offenseLevel)
            ->first()
            ?? DisciplinarySanction::where('violation_type_id', $violationTypeId)
                ->orderBy('disciplinary_sanction_id')
                ->first();
    }

    /**
     * Derive a completion deadline from a sanction description when it states
     * a calendar duration, such as "one month suspension" or "two weeks
     * community service". Returns null for sanctions without a date-like
     * duration, leaving the administrator's field optional.
     */
    private function deadlineFromSanctionText(?string $sanctionText, Carbon $start): ?Carbon
    {
        $text = strtolower(trim((string) $sanctionText));
        if ($text === '') {
            return null;
        }

        $numberWords = [
            'a' => 1,
            'an' => 1,
            'one' => 1,
            'two' => 2,
            'three' => 3,
            'four' => 4,
            'five' => 5,
            'six' => 6,
            'seven' => 7,
            'eight' => 8,
            'nine' => 9,
            'ten' => 10,
            'eleven' => 11,
            'twelve' => 12,
        ];

        if (! preg_match('/\b(\d+|a|an|one|two|three|four|five|six|seven|eight|nine|ten|eleven|twelve)\s+(day|week|month|year)s?\b/i', $text, $matches)) {
            return null;
        }

        $amount = is_numeric($matches[1])
            ? (int) $matches[1]
            : ($numberWords[strtolower($matches[1])] ?? null);

        if ($amount === null || $amount < 1 || $amount > 3650) {
            return null;
        }

        $unit = strtolower($matches[2]);
        $deadline = $start->copy()->startOfDay();

        return match ($unit) {
            'day' => $deadline->addDays($amount)->endOfDay(),
            'week' => $deadline->addWeeks($amount)->endOfDay(),
            'month' => $deadline->addMonths($amount)->endOfDay(),
            'year' => $deadline->addYears($amount)->endOfDay(),
            default => null,
        };
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

    /**
     * Mark the latest submitted (unreviewed) proof of a sanction record as
     * accepted or rejected, preserving the full proof history.
     */
    private function markLatestProofReviewed(
        StudentSanctionRecord $record,
        int $adminId,
        string $decision,
        ?string $remarks
    ): void {
        $proof = SanctionProof::where('record_id', $record->record_id)
            ->where('status', SanctionProof::STATUS_SUBMITTED)
            ->latest('submitted_at')
            ->latest('proof_id')
            ->first();

        if (! $proof) {
            return;
        }

        $proof->update([
            'status' => $decision === 'accepted'
                ? SanctionProof::STATUS_ACCEPTED
                : SanctionProof::STATUS_REJECTED,
            'reviewed_by_admin_id' => $adminId,
            'reviewed_at' => now(),
            'review_remarks' => $remarks,
        ]);
    }

    /**
     * Serve the current proof-of-completion file from private storage.
     *
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function downloadProof(StudentSanctionRecord $studentSanctionRecord, Request $request)
    {
        if ($studentSanctionRecord->proof_path === null || $studentSanctionRecord->proof_path === '') {
            abort(404);
        }

        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = \Illuminate\Support\Facades\Storage::disk((string) config('filesystems.evidence_disk', 'local'));

        if (! $disk->exists($studentSanctionRecord->proof_path)) {
            abort(404);
        }

        // Audit the access (admin reviewing a proof of completion).
        try {
            app(\App\Services\EvidenceSecurityService::class)->logEvent(
                \App\Models\EvidenceSecurityEvent::PROOF_VIEWED,
                $studentSanctionRecord->violation_id,
                null,
                'info',
                $request,
                ['record_id' => $studentSanctionRecord->record_id],
                'admin',
                (int) Auth::guard('admin')->id(),
            );
        } catch (\Throwable $e) {
            // Logging must never break the proof view.
        }

        return $disk->response(
            $studentSanctionRecord->proof_path,
            $studentSanctionRecord->proof_original_name ?: basename($studentSanctionRecord->proof_path)
        );
    }
}
