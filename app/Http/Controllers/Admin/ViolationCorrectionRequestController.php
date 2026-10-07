<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Models\AuditLog;
use App\Models\StudentSanctionRecord;
use App\Models\Violation;
use App\Models\ViolationCorrectionRequest;
use App\Models\ViolationEditHistory;
use App\Services\StudentNotificationService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ViolationCorrectionRequestController extends Controller
{
    /**
     * Display pending student correction requests.
     */
    public function index()
    {
        $requests = ViolationCorrectionRequest::with([
            'violation.student.studentInfo.program',
            'violation.violationType.violationCategory',
            'requestedViolationType.violationCategory',
            'reviewer',
        ])
            ->where(
                'status',
                ViolationCorrectionRequest::STATUS_PENDING
            )
            ->latest('request_id')
            ->paginate(15);

        return view(
            'admin.violations.correction-requests.index',
            compact('requests')
        );
    }

    /**
     * Approve and apply a correction request.
     */
    public function approve(
        Request $request,
        ViolationCorrectionRequest $correctionRequest
    ): RedirectResponse {

        $adminId = auth('admin')->id();

        abort_unless(
            $adminId !== null,
            403,
            'Administrator authentication is required.'
        );

        $validated = $request->validate([
            'review_remarks' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        try {

            $result = DB::transaction(function () use (
                $correctionRequest,
                $adminId,
                $validated
            ) {

                /*
                 * Lock the request so two administrators cannot process
                 * it simultaneously.
                 */
                $lockedRequest =
                    ViolationCorrectionRequest::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $correctionRequest->request_id
                        );

                if (
                    $lockedRequest->status
                    !== ViolationCorrectionRequest::STATUS_PENDING
                ) {
                    throw new \RuntimeException(
                        'This correction request has already been reviewed.'
                    );
                }

                /*
                 * Lock the actual violation.
                 */
                $violation =
                    Violation::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $lockedRequest->violation_id
                        );

                /*
                 * Make sure the request still belongs to the same student.
                 */
                if (
                    (string) $violation->student_number
                    !==
                    (string) $lockedRequest->student_number
                ) {
                    throw new \RuntimeException(
                        'The correction request does not match the student attached to the violation.'
                    );
                }

                /*
                 * Only effective violations can be corrected.
                 */
                if (! $violation->is_effective) {
                    throw new \RuntimeException(
                        'This violation is no longer eligible for correction.'
                    );
                }

                $oldTypeId =
                    $violation->violation_type_id;

                $oldDate =
                    $violation->violation_date;

                $oldDescription =
                    $violation->description;

                $newTypeId =
                    $lockedRequest->requested_violation_type_id
                    ?? $oldTypeId;

                $newDate =
                    $lockedRequest->requested_violation_date
                    ?? $oldDate;

                $newDescription =
                    $lockedRequest->requested_description;

                /*
                 * A sanction already exists for this violation.
                 * Type/date must remain unchanged.
                 */
                $hasAssignedSanction =
                    StudentSanctionRecord::where(
                        'violation_id',
                        $violation->violation_id
                    )->exists();

                if ($hasAssignedSanction) {

                    $typeChanged =
                        (string) $newTypeId
                        !== (string) $oldTypeId;

                    $dateChanged = false;

                    if (
                        $oldDate !== null &&
                        $newDate !== null
                    ) {
                        $dateChanged =
                            Carbon::parse($oldDate)
                                ->format('Y-m-d H:i:s')
                            !==
                            Carbon::parse($newDate)
                                ->format('Y-m-d H:i:s');
                    }

                    if ($typeChanged || $dateChanged) {
                        throw new \RuntimeException(
                            'This correction cannot be approved because a sanction has already been assigned. The violation type and date are locked.'
                        );
                    }

                    $newTypeId = $oldTypeId;
                    $newDate = $oldDate;
                }

                /*
                 * Prevent the correction from creating a duplicate
                 * same-day incident.
                 *
                 * Same student +
                 * same violation type +
                 * same calendar day
                 *
                 * Existing denied records are ignored.
                 */
                $duplicateExists =
                    Violation::query()
                        ->where(
                            'student_number',
                            $violation->student_number
                        )
                        ->where(
                            'violation_type_id',
                            $newTypeId
                        )
                        ->whereDate(
                            'violation_date',
                            Carbon::parse($newDate)
                                ->toDateString()
                        )
                        ->where(
                            'violation_id',
                            '!=',
                            $violation->violation_id
                        )
                        ->notDenied()
                        ->exists();

                if ($duplicateExists) {
                    throw new \RuntimeException(
                        'The requested correction would create a duplicate violation for the same student, violation type, and calendar day.'
                    );
                }

                /*
                 * Determine whether anything actually changed.
                 */
                $typeChanged =
                    (string) $oldTypeId
                    !== (string) $newTypeId;

                $dateChanged = false;

                if (
                    $oldDate !== null &&
                    $newDate !== null
                ) {
                    $dateChanged =
                        Carbon::parse($oldDate)
                            ->format('Y-m-d H:i:s')
                        !==
                        Carbon::parse($newDate)
                            ->format('Y-m-d H:i:s');
                }

                $descriptionChanged =
                    trim((string) ($oldDescription ?? ''))
                    !==
                    trim((string) ($newDescription ?? ''));

                if (
                    ! $typeChanged &&
                    ! $dateChanged &&
                    ! $descriptionChanged
                ) {
                    throw new \RuntimeException(
                        'No actual correction remains to be applied.'
                    );
                }

                /*
                 * FIRST:
                 * Preserve the original values.
                 */
                ViolationEditHistory::create([
                    'violation_id' =>
                        $violation->violation_id,

                    'admin_id' =>
                        $adminId,

                    'old_violation_type_id' =>
                        $oldTypeId,

                    'old_violation_date' =>
                        $oldDate,

                    'old_description' =>
                        $oldDescription,

                    'new_violation_type_id' =>
                        $newTypeId,

                    'new_violation_date' =>
                        $newDate,

                    'new_description' =>
                        $newDescription,

                    'reason' =>
                        trim($lockedRequest->reason),
                ]);

                /*
                 * SECOND:
                 * Apply the approved correction.
                 */
                $violation->update([
                    'violation_type_id' =>
                        $newTypeId,

                    'violation_date' =>
                        $newDate,

                    'description' =>
                        $newDescription !== null
                            ? trim((string) $newDescription)
                            : null,
                ]);

                /*
                 * THIRD:
                 * Mark the correction request as approved.
                 */
                $lockedRequest->update([
                    'status' =>
                        ViolationCorrectionRequest::STATUS_APPROVED,

                    'reviewed_by_admin_id' =>
                        $adminId,

                    'reviewed_at' =>
                        now(),

                    'review_remarks' =>
                        isset($validated['review_remarks'])
                            ? trim(
                                $validated['review_remarks']
                            )
                            : null,
                ]);

                /*
                 * FOURTH:
                 * Write the permanent audit record.
                 */
                AuditLog::create([
                    'actor_type' =>
                        'admin',

                    'actor_id' =>
                        $adminId,

                    'action' =>
                        'Approve Violation Correction Request',

                    'module' =>
                        'Violations',

                    'description' =>
                        'Approved correction request #'
                        . $lockedRequest->request_id
                        . ' and corrected violation #'
                        . $violation->violation_id
                        . ' for student '
                        . ($violation->student_number ?? 'unknown')
                        . '. Reason: '
                        . trim($lockedRequest->reason)
                        . '.',
                ]);

                return [
                    'request' =>
                        $lockedRequest,

                    'violation' =>
                        $violation,
                ];
            });

            /*
             * Notify the student AFTER the transaction succeeds.
             */
            try {

                app(StudentNotificationService::class)->create(
                    studentNumber:
                        $result['violation']->student_number,

                    type:
                        'violation_correction',

                    title:
                        'Correction Request Approved',

                    message:
                        'Your violation correction request has been approved by the administrator. The requested correction has been applied to your violation record.',

                    targetType:
                        'violation_correction_request',

                    targetId:
                        (int) $result['request']->request_id,
                );

            } catch (\Throwable $e) {

                Log::warning(
                    'Failed to notify student about approved correction request.',
                    [
                        'request_id' =>
                            $result['request']->request_id,

                        'error' =>
                            $e->getMessage(),
                    ]
                );
            }

            return back()->with(
                'success',
                'The correction request was approved and the violation record was updated. The original values were preserved in the edit history.'
            );

        } catch (\RuntimeException $e) {

            return back()->withErrors([
                'correction_request' =>
                    $e->getMessage(),
            ]);

        } catch (\Throwable $e) {

            Log::error(
                'Violation correction approval failed.',
                [
                    'request_id' =>
                        $correctionRequest->request_id,

                    'error' =>
                        $e->getMessage(),
                ]
            );

            return back()->withErrors([
                'correction_request' =>
                    'The correction could not be applied. No violation data was changed.',
            ]);
        }
    }

    /**
     * Reject a correction request.
     *
     * The actual violation is never changed.
     */
    public function reject(
        Request $request,
        ViolationCorrectionRequest $correctionRequest
    ): RedirectResponse {

        $adminId = auth('admin')->id();

        abort_unless(
            $adminId !== null,
            403,
            'Administrator authentication is required.'
        );

        $validated = $request->validate([
            'review_remarks' => [
                'required',
                'string',
                'min:10',
                'max:2000',
            ],
        ], [
            'review_remarks.required' =>
                'Please provide a reason for rejecting the correction request.',

            'review_remarks.min' =>
                'The rejection reason must contain at least 10 characters.',
        ]);

        try {

            $updatedRequest = DB::transaction(
                function () use (
                    $correctionRequest,
                    $adminId,
                    $validated
                ) {

                    $lockedRequest =
                        ViolationCorrectionRequest::query()
                            ->lockForUpdate()
                            ->findOrFail(
                                $correctionRequest->request_id
                            );

                    if (
                        $lockedRequest->status
                        !==
                        ViolationCorrectionRequest::STATUS_PENDING
                    ) {
                        throw new \RuntimeException(
                            'This correction request has already been reviewed.'
                        );
                    }

                    $lockedRequest->update([
                        'status' =>
                            ViolationCorrectionRequest::STATUS_REJECTED,

                        'reviewed_by_admin_id' =>
                            $adminId,

                        'reviewed_at' =>
                            now(),

                        'review_remarks' =>
                            trim(
                                $validated['review_remarks']
                            ),
                    ]);

                    AuditLog::create([
                        'actor_type' =>
                            'admin',

                        'actor_id' =>
                            $adminId,

                        'action' =>
                            'Reject Violation Correction Request',

                        'module' =>
                            'Violations',

                        'description' =>
                            'Rejected correction request #'
                            . $lockedRequest->request_id
                            . ' for violation #'
                            . $lockedRequest->violation_id
                            . '. Reason: '
                            . trim(
                                $validated['review_remarks']
                            )
                            . '.',
                    ]);

                    return $lockedRequest;
                }
            );

            try {

                app(StudentNotificationService::class)->create(
                    studentNumber:
                        $updatedRequest->student_number,

                    type:
                        'violation_correction',

                    title:
                        'Correction Request Rejected',

                    message:
                        'Your violation correction request was reviewed by the administrator and was not approved. Please review the administrator remarks.',

                    targetType:
                        'violation_correction_request',

                    targetId:
                        (int) $updatedRequest->request_id,
                );

            } catch (\Throwable $e) {

                Log::warning(
                    'Failed to notify student about rejected correction request.',
                    [
                        'request_id' =>
                            $updatedRequest->request_id,

                        'error' =>
                            $e->getMessage(),
                    ]
                );
            }

            return back()->with(
                'success',
                'The correction request was rejected. The violation record was not changed.'
            );

        } catch (\RuntimeException $e) {

            return back()->withErrors([
                'correction_request' =>
                    $e->getMessage(),
            ]);

        } catch (\Throwable $e) {

            Log::error(
                'Violation correction rejection failed.',
                [
                    'request_id' =>
                        $correctionRequest->request_id,

                    'error' =>
                        $e->getMessage(),
                ]
            );

            return back()->withErrors([
                'correction_request' =>
                    'The correction request could not be rejected.',
            ]);
        }
    }
}