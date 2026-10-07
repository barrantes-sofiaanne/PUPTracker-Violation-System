<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\Violation;
use App\Models\User;
use App\Models\DisciplinarySanction;
use App\Models\StudentSanctionRecord;
use App\Models\SanctionRequest;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\AdminNotificationService;
use App\Models\ViolationEvidence;
use App\Models\ViolationCorrectionRequest;
use App\Models\ViolationType;
class StudentRecordController extends Controller
{
    public function index()
    {
        // Flip this student's In Progress/Pending sanctions past deadline to
        // Overdue (one-time notifications) before rendering.
        try {
            app(\App\Services\SanctionOverdueService::class)->sweep();
        } catch (\Throwable $e) {
            // Sweep failures must never block the record page.
        }

        /** @var User $user */
        $user = Auth::user();

       $user->load([
           'studentInfo.program',
           'studentInfo.year',
           'studentInfo.section',
       ]);

       $violationTypes = ViolationType::with('violationCategory')
    ->orderBy('violation_type')
    ->get();

$pendingCorrectionRequests = ViolationCorrectionRequest::where(
    'student_number',
    $user->student_number
)
    ->where('status', ViolationCorrectionRequest::STATUS_PENDING)
    ->get()
    ->keyBy('violation_id');

$assignedSanctionViolationIds = StudentSanctionRecord::where(
    'student_number',
    $user->student_number
)
    ->whereNotNull('violation_id')
    ->pluck('violation_id')
    ->mapWithKeys(fn ($id) => [(int) $id => true])
    ->all();

       $violations = Violation::with([
    'violationType.violationCategory',
    'violationType.disciplinarySanctions',
    'evidence',
])
           ->where('student_number', $user->student_number)
           // Individual Violations Log: show confirmed, legacy (NULL), AND
           // pending (awaiting admin confirmation). Denied rows are excluded.
           ->notDenied()
           ->orderByDesc('violation_date')
           ->paginate(10);
/*
|--------------------------------------------------------------------------
| Summary by Violation
|--------------------------------------------------------------------------
*/

$violationSummary = [];

$activeRequests = SanctionRequest::where(
    'student_number',
    $user->student_number
)
->where('is_active', 1)
->pluck('violation_type_id')
->map(fn ($value) => (string) $value)
->toArray();

$approvedRequests = SanctionRequest::where(
    'student_number',
    $user->student_number
)
->where('is_active', 0)
->where('status', 'Approved')
->pluck('violation_type_id')
->map(fn ($value) => (string) $value)
->toArray();

// Violation types that already have ANY assigned sanction record (open or
// completed). Once a violation type has entered the sanction workflow it
// belongs in the Sanction Record tab only and must NOT be re-requestable
// (including after the sanction has been completed).
$assignedSanctionTypeKeys = StudentSanctionRecord::with('violation')
    ->where('student_number', $user->student_number)
    ->get()
    ->flatMap(function ($record) {
$resolvedType = $record->violation?->resolvedViolationType();

$resolvedTypeId = (string) ($resolvedType?->violation_type_id ?? '');
$resolvedTypeName = (string) ($resolvedType?->violation_type ?? '');

return array_filter([
    $resolvedTypeId,
    $resolvedTypeName,
    strtolower($resolvedTypeName),
]);
    })
    ->unique()
    ->values()
    ->toArray();

$matchesViolationType = function ($violationRecord, array $pool): bool {
 $resolvedType = $violationRecord->resolvedViolationType();

$resolvedTypeId = (string) ($resolvedType?->violation_type_id ?? '');
$resolvedTypeName = (string) ($resolvedType?->violation_type ?? '');

$candidates = array_filter([
    $resolvedTypeId,
    $resolvedTypeName,
    strtolower($resolvedTypeName),
]);

    foreach ($candidates as $candidate) {
        if (in_array($candidate, $pool, true)) {
            return true;
        }
    }

    return false;
};

// Group ALL of the student's non-denied violations by violation type so the
// Summary can distinguish:
//   - pending violations (awaiting admin confirmation)  -> show as pending
//   - effective violations that still need action       -> sanctionable
// Types whose only disciplinary outcome is a Warning, or that already have a
// COMPLETED sanction, are removed from the Summary table (they remain in the
// Individual Violations Log / Sanction Record tabs).
$groupedViolations = Violation::with([
        'violationType.violationCategory',
        'violationType.disciplinarySanctions'
    ])
    ->where('student_number', $user->student_number)
    ->notDenied()
    ->get()
    ->groupBy(function ($violation) {
        $resolvedType = $violation->resolvedViolationType();

        if (!empty($resolvedType?->violation_type_id)) {
            return 'id:' . $resolvedType->violation_type_id;
        }

return 'unknown:' . $violation->violation_id;    });

foreach ($groupedViolations as $records) {

    // Split this type's records into pending (awaiting admin confirmation)
    // and effective (confirmed/legacy) so offense levels are computed only
    // from confirmed records.
    $pendingRecords = $records->filter(function ($v) {
        return strtolower((string) $v->status) === Violation::STATUS_PENDING;
    })->values();

    $effectiveRecords = $records->filter(function ($v) {
        return strtolower((string) $v->status) !== Violation::STATUS_PENDING;
    })->values();

    $hasPending = $pendingRecords->count() > 0;
    $hasEffective = $effectiveRecords->count() > 0;

    // The Summary by Violation is grouped by violation type, but a correction
    // request must always target one concrete violation record. Prefer an
    // already-pending correction request when one exists; otherwise use the
    // most recent effective violation represented by this summary row.
    $correctionViolation = null;
    if ($hasEffective) {
        $correctionViolation = $effectiveRecords
            ->first(function ($violation) use ($pendingCorrectionRequests) {
                return $pendingCorrectionRequests->has($violation->violation_id);
            });

        $correctionViolation ??= $effectiveRecords
            ->sortByDesc('violation_date')
            ->first();
    }

    $hasPendingCorrection = $correctionViolation
        ? $pendingCorrectionRequests->has($correctionViolation->violation_id)
        : false;

    $hasAssignedSanction = $correctionViolation
        ? isset($assignedSanctionViolationIds[$correctionViolation->violation_id])
        : false;

    // No confirmed record yet -> the whole type is awaiting confirmation.
    if (! $hasEffective) {
        $first = $pendingRecords->first();
        $count = 0;
        $offenseLevel = '—';
    } else {
        $first = $effectiveRecords->first();
        $count = $effectiveRecords->count();
        $offenseLevel = match ($count) {
            1 => '1st Offense',
            2 => '2nd Offense',
            3 => '3rd Offense',
            default => $count . 'th Offense'
        };
    }

    $resolvedType = $first->resolvedViolationType();

    $sanction = null;
    if (!empty($resolvedType?->violation_type_id)) {
        $sanction = DisciplinarySanction::where('violation_type_id', $resolvedType->violation_type_id)
            ->where('offense_level', $offenseLevel)
            ->first()
            ?? DisciplinarySanction::where('violation_type_id', $resolvedType->violation_type_id)
                ->orderBy('disciplinary_sanction_id')
                ->first();
    }

    $disciplinarySanction = $sanction->disciplinary_sanction ?? 'N/A';

    $status = 'Unknown';
    if (str_contains(strtolower($disciplinarySanction), 'warning')) {
        $status = 'Warning';
    } elseif ($disciplinarySanction !== 'N/A') {
        $status = 'Sanction';
    }

    // Warning-only violations have no sanction workflow; keep them out of the
    // Summary table (they are still listed in the Individual Violations Log).
    if ($status === 'Warning') {
        continue;
    }

    if (
        $matchesViolationType($first, $approvedRequests)
        || $matchesViolationType($first, $assignedSanctionTypeKeys)
    ) {
        // A violation type that already entered the sanction workflow (open
        // OR completed) belongs to the Sanction Record tab only. Completed
        // sanctions are therefore removed from the Summary table.
        continue;
    }

    // Pending-confirmation rows are shown in the Summary so the student sees
    // the violation is awaiting admin review; they cannot request a sanction
    // until the violation is confirmed.
    if (! $hasEffective) {
        $workflowStatus = 'PendingConfirmation';
    } else {
        $workflowStatus = $matchesViolationType($first, $activeRequests)
            ? 'Requested'
            : 'Actionable';
    }

    $violationSummary[] = [
        // Using ?-> prevents the "reading property on null" error
        'category' => $first->violation_category_display,

        'type' => $first->violation_type_display,

        'violation_type_id' => $resolvedType?->violation_type_id,

        // Concrete violation used by the Summary -> Request Correction action.
        'correction_violation_id' => $correctionViolation?->violation_id,
        'correction_violation_type_id' => $correctionViolation?->violation_type_id,
        'correction_violation_date' => $correctionViolation?->violation_date,
        'correction_description' => $correctionViolation?->description,
        'has_pending_correction' => $hasPendingCorrection,
        'has_assigned_sanction' => $hasAssignedSanction,

        'offense_level' => $offenseLevel,

        'remarks' => $hasPending
            ? ($hasEffective ? '(New record awaiting confirmation)' : ($first->description ?: 'Awaiting confirmation'))
            : ($count > 1 ? '(Multiple instances - see log)' : ($first->description ?: 'No remarks')),

        'disciplinary_sanction' => $disciplinarySanction,

        'violation_status' => $status,

        'workflow_status' => $workflowStatus,

        'has_pending' => $hasPending,
    ];
}

$sanctionRecords = StudentSanctionRecord::with([
    'violation.violationType',
    'assignedSanction',
    'completionReviewer',
])
->where(
    'student_number',
    $user->student_number
)
->orderByDesc('date_assigned')
->get();

return view(
    'student.record',
    compact(
        'user',
        'violations',
        'violationSummary',
        'sanctionRecords',
        'violationTypes',
        'pendingCorrectionRequests',
        'assignedSanctionViolationIds'
    )
);
    }

public function requestCorrection(
    Request $request,
    Violation $violation
) {
    /** @var User $user */
    $user = Auth::user();

    /*
     * The authenticated student must own the violation.
     *
     * This prevents URL manipulation such as:
     * /student/violations/123/correction-request
     *
     * when violation #123 belongs to another student.
     */
    if (
        ! $user instanceof User ||
        (string) $violation->student_number !== (string) $user->student_number
    ) {
        abort(403, 'You are not authorized to request a correction for this violation.');
    }

    /*
     * Inactive student accounts cannot create new requests.
     */
    if (! $user->isActiveAccount()) {
        return back()->withErrors([
            'correction_request' =>
                'A correction request cannot be submitted because your account is inactive.',
        ]);
    }

    /*
     * Only effective violations may be disputed.
     *
     * Pending = still awaiting administrative confirmation.
     * Denied  = not part of the official record.
     */
    if (! $violation->is_effective) {
        return back()->withErrors([
            'correction_request' =>
                'Only confirmed violations can be submitted for correction.',
        ]);
    }

    /*
     * Do not allow multiple unresolved correction requests for the same
     * violation.
     */
    $alreadyPending = ViolationCorrectionRequest::where(
        'violation_id',
        $violation->violation_id
    )
        ->where('student_number', $user->student_number)
        ->where('status', ViolationCorrectionRequest::STATUS_PENDING)
        ->exists();

    if ($alreadyPending) {
        return back()->withErrors([
            'correction_request' =>
                'A correction request for this violation is already pending review.',
        ]);
    }

    $validated = $request->validate([
        'requested_violation_type_id' => [
            'required',
            'integer',
            'exists:violation_type_tbl,violation_type_id',
        ],

        'requested_violation_date' => [
            'required',
            'date',
        ],

        'requested_description' => [
            'nullable',
            'string',
            'max:5000',
        ],

        'reason' => [
            'required',
            'string',
            'min:10',
            'max:2000',
        ],
    ], [
        'requested_violation_type_id.required' =>
            'Please select the corrected violation type.',

        'requested_violation_date.required' =>
            'Please provide the corrected violation date.',

        'reason.required' =>
            'Please explain why the violation record should be corrected.',

        'reason.min' =>
            'The correction reason must contain at least 10 characters.',
    ]);

    /*
     * If a sanction has already been assigned to this violation, changing
     * the type or date could invalidate the basis of that sanction.
     *
     * The existing Admin correction workflow already protects these fields.
     * We enforce the same rule here so a student cannot bypass it.
     */
    $hasAssignedSanction = StudentSanctionRecord::where(
        'violation_id',
        $violation->violation_id
    )->exists();

    if ($hasAssignedSanction) {
        $requestedDate = \Carbon\Carbon::parse(
            $validated['requested_violation_date']
        );

        $currentDate = $violation->violation_date
            ? \Carbon\Carbon::parse($violation->violation_date)
            : null;

        $typeChanged =
            (string) $validated['requested_violation_type_id']
            !== (string) $violation->violation_type_id;

        $dateChanged =
            $currentDate &&
            $currentDate->format('Y-m-d H:i:s')
            !== $requestedDate->format('Y-m-d H:i:s');

        if ($typeChanged || $dateChanged) {
            return back()->withErrors([
                'correction_request' =>
                    'The violation type and date cannot be changed through a correction request because a sanction has already been assigned. You may request correction of the description or factual remarks.',
            ])->withInput();
        }
    }

    $requestedDescription = trim(
        (string) ($validated['requested_description'] ?? '')
    );

    $requestedDate = \Carbon\Carbon::parse(
        $validated['requested_violation_date']
    );

    /*
     * Prevent meaningless requests where the requested values are identical
     * to the existing record.
     */
    $sameType =
        (string) $violation->violation_type_id
        === (string) $validated['requested_violation_type_id'];

    $sameDate =
        $violation->violation_date
        && \Carbon\Carbon::parse($violation->violation_date)
            ->format('Y-m-d H:i:s')
        === $requestedDate->format('Y-m-d H:i:s');

    $sameDescription =
        trim((string) ($violation->description ?? ''))
        === $requestedDescription;

    if ($sameType && $sameDate && $sameDescription) {
        return back()->withErrors([
            'correction_request' =>
                'No correction was requested because the submitted values are the same as the existing violation record.',
        ])->withInput();
    }

    $correctionRequest = ViolationCorrectionRequest::create([
        'violation_id' => $violation->violation_id,
        'student_number' => $user->student_number,

        'requested_violation_type_id' =>
            (int) $validated['requested_violation_type_id'],

        'requested_violation_date' => $requestedDate,

        'requested_description' =>
            $requestedDescription !== ''
                ? $requestedDescription
                : null,

        'reason' => trim($validated['reason']),

        'status' => ViolationCorrectionRequest::STATUS_PENDING,
    ]);

    /*
     * Notify OSS Staff.
     *
     * OSS Staff accounts are operational ADMIN accounts in this project.
     */
    try {
        app(\App\Services\AdminNotificationService::class)->create(
            type: 'violation_correction_request',
            title: 'Violation Correction Request',
            message:
                trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''))
                . ' (' . $user->student_number . ') submitted a correction request for violation #'
                . $violation->violation_id
                . '.',
            targetType: 'violation_correction_request',
            targetId: (int) $correctionRequest->request_id,
        );
    } catch (\Throwable $e) {
        Log::warning(
            'Failed to create violation correction request admin notification',
            [
                'request_id' => $correctionRequest->request_id,
                'error' => $e->getMessage(),
            ]
        );
    }

    /*
     * Notify the student that the request has been submitted.
     */
    try {
        app(\App\Services\StudentNotificationService::class)->create(
            studentNumber: $user->student_number,
            type: 'violation_correction',
            title: 'Correction Request Pending',
            message:
                'Your violation correction request has been submitted and is now pending review by the Office of Student Services.',
            targetType: 'violation_correction_request',
            targetId: (int) $correctionRequest->request_id,
            userId: (int) $user->user_id,
        );
    } catch (\Throwable $e) {
        Log::warning(
            'Failed to create student correction request notification',
            [
                'request_id' => $correctionRequest->request_id,
                'student_number' => $user->student_number,
                'error' => $e->getMessage(),
            ]
        );
    }

    return back()->with(
        'success',
        'Your correction request has been submitted. The Office of Student Services will review it.'
    );
}

    /**
     * Submit a proof of completion for a pending disciplinary sanction.
     *
     * The uploaded file and optional notes are attached to the sanction record
     * and an administrator is notified so they can accept or reject the proof.
     *
     * ONLY Community Service / Campus Service sanctions accept a proof. For
     * every other sanction the student is instead directed to the Office of
     * Student Services for a personal consultation (see
     * StudentSanctionRecord::CONSULTATION_NOTICE).
     */
    public function submitProof(Request $request)
    {
        // SECURITY: only record_id, the proof file, and optional notes are ever
        // read from the student. The designated office and starting date are
        // ADMIN-controlled assignment fields and MUST come from the server-side
        // StudentSanctionRecord — a student can never change them, even if they
        // inject designated_office/starting_date into this request. Ownership is
        // enforced below by scoping on the authenticated student_number.
        $request->validate([
            'record_id' => ['required', 'integer'],
            'proof_file' => [
                'required',
                'file',
                // Sanction completion proof: signed official forms/photos.
                // DOC/DOCX/GIF are NOT accepted — only JPG/JPEG/PNG/PDF.
                'mimes:jpg,jpeg,png,pdf',
                'mimetypes:image/jpeg,image/png,application/pdf',
                'max:5120',
            ],
            'proof_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        /** @var User $user */
        $user = Auth::user();

        $record = StudentSanctionRecord::with(['violation', 'assignedSanction'])
            ->where('record_id', $request->integer('record_id'))
            ->where('student_number', $user->student_number)
            ->first();

        if (!$record) {
            return response()->json([
                'success' => false,
                'message' => 'Sanction record not found.',
            ], 404);
        }

        if (! $record->is_open) {
            return response()->json([
                'success' => false,
                'message' => 'This sanction is already completed and cannot accept a new proof.',
            ], 422);
        }

        // Only Community Service / Campus Service sanctions take a proof of
        // completion. Every other sanction is processed through a personal
        // consultation at the Office of Student Services, so a proof upload is
        // rejected server-side even if the client posts one.
        if (! $record->requires_proof_of_completion) {
            return response()->json([
                'success' => false,
                'message' => StudentSanctionRecord::CONSULTATION_NOTICE,
            ], 422);
        }

        try {
            $file = $request->file('proof_file');

            // Production guard FIRST: never write confidential proof to an
            // ephemeral filesystem just because R2 is misconfigured.
            \App\Support\Storage\EvidenceDisk::assertWritable();

            $disk = \App\Support\Storage\EvidenceDisk::disk();
            $directory = 'sanction-proofs/' . $record->record_id;

            // Official uploaded document — ALWAYS preserved byte-for-byte.
            // We never re-encode/watermark the signed Community Service form.
            $originalBytes = (string) $file->getContent();
            $sha256 = hash('sha256', $originalBytes);
            $proofCode = \App\Models\SanctionProof::nextProofCode();

            $extension = strtolower($file->getClientOriginalExtension());
            // Server-side filename derived from the unique proof code — the
            // original filename is never trusted or used on disk. Because the
            // code is unique per submission, a RESUBMISSION writes a NEW object
            // and can never overwrite the previous proof.
            $filename = $proofCode . '.' . ($extension !== '' ? $extension : 'bin');

            $storedPath = $directory . '/' . $filename;

            if (! $disk->put($storedPath, $originalBytes)) {
                throw new \RuntimeException('Unable to store proof file.');
            }

            // Step 3 of the required flow: CONFIRM the object really exists on
            // the configured disk before any database row references it. A
            // misleading record pointing at a missing object is worse than a
            // failed upload, because it looks like data loss to the student.
            if (! $disk->exists($storedPath)) {
                try {
                    $disk->delete($storedPath);
                } catch (\Throwable $ignored) {
                    // Best-effort cleanup only.
                }

                throw new \RuntimeException('Stored proof could not be verified on the configured disk.');
            }

            $notes = trim((string) $request->input('proof_notes', ''));

            // If the record was never assigned (still Pending), submitting a
            // proof implies it is now active/in progress.
            if (strcasecmp((string) $record->status, \App\Models\StudentSanctionRecord::STATUS_PENDING) === 0) {
                $record->status = \App\Models\StudentSanctionRecord::STATUS_IN_PROGRESS;
            }

            // Steps 4-6 of the required flow run inside ONE transaction so the
            // "current proof" pointer and the historical audit row are written
            // together. A pointer that claims a proof was submitted, with no
            // matching history row, would break the audit trail the brief
            // requires to stay intact.
            try {
                DB::transaction(function () use (
                    $record,
                    $storedPath,
                    $file,
                    $notes,
                    $proofCode,
                    $sha256
                ): void {
                    $record->update([
                        'status' => $record->status,
                        'proof_path' => $storedPath,
                        'proof_original_name' => $file->getClientOriginalName(),
                        'proof_submitted_at' => now(),
                        'proof_notes' => $notes,
                        'completion_reviewed_by_admin_id' => null,
                        'completion_reviewed_at' => null,
                        'completion_remarks' => null,
                    ]);

                    // Preserve per-submission proof history. Each submission is a
                    // new auditable row with its own PROOF code + SHA-256; the
                    // uploaded original is never modified, so a resubmission
                    // cannot overwrite the previous proof object.
                    \App\Models\SanctionProof::create([
                        'proof_code' => $proofCode,
                        'record_id' => $record->record_id,
                        'student_number' => $record->student_number,
                        'disk_path' => $storedPath,
                        'original_name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getMimeType(),
                        'file_size' => $file->getSize(),
                        'sha256_hash' => $sha256,
                        'notes' => $notes,
                        'status' => \App\Models\SanctionProof::STATUS_SUBMITTED,
                        'submitted_at' => now(),
                    ]);
                });
            } catch (\Throwable $dbFailure) {
                // The object is already on the configured disk but nothing in the
                // database points at it. Remove it so we do not accumulate
                // orphaned confidential files, then surface the failure.
                try {
                    $disk->delete($storedPath);
                } catch (\Throwable $cleanup) {
                    \Illuminate\Support\Facades\Log::error(
                        'Orphaned proof object could not be removed after a database failure.',
                        [
                            'record_id' => $record->record_id,
                            'key_hash' => hash('sha256', $storedPath),
                            'error' => $cleanup->getMessage(),
                        ]
                    );
                }

                throw $dbFailure;
            }

            // Record an evidence security event for the proof submission.
            try {
                app(\App\Services\EvidenceSecurityService::class)->logEvent(
                    \App\Models\EvidenceSecurityEvent::PROOF_SUBMITTED,
                    $record->violation_id,
                    null,
                    'info',
                    $request,
                    [
                        'record_id' => $record->record_id,
                        'proof_code' => $proofCode,
                        'sha256' => $sha256,
                    ],
                    'student',
                    (int) ($user->user_id ?? $user->getAuthIdentifier()),
                );
            } catch (\Throwable $e) {
                // Logging must never break the submission.
            }

            // Notify the admin so they can review the submitted proof.
            try {
                $studentName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
                app(AdminNotificationService::class)->create(
                    type: 'completion_proof',
                    title: 'Sanction Proof of Completion',
                    message: $studentName . ' (' . $user->student_number . ') submitted a proof of completion for a sanction. Review and accept or reject it.',
                    targetType: 'sanction_record',
                    targetId: (int) $record->record_id,
                );
            } catch (\Throwable $e) {
                // Notification must never break the submission.
            }

            return response()->json([
                'success' => true,
                'message' => 'Proof of completion submitted. You will be notified once it is reviewed.',
                'record_id' => $record->record_id,
            ]);
        } catch (\Throwable $e) {
            Log::error('Student sanction proof upload failed', [
                'record_id' => $record->record_id ?? null,
                'error' => \Illuminate\Support\Str::limit($e->getMessage(), 300),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to upload the proof of completion. Please try again.',
            ], 500);
        }
    }

    /**
     * Serve a sanction proof file from private storage to the owning student.
     *
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function downloadProof(StudentSanctionRecord $studentSanctionRecord, Request $request)
    {
        if ((string) $studentSanctionRecord->student_number !== (string) Auth::user()->student_number) {
            abort(403);
        }

        if ($studentSanctionRecord->proof_path === null || $studentSanctionRecord->proof_path === '') {
            return \App\Services\PrivateFileResponder::stream(
                'proof',
                null,
                $studentSanctionRecord->proof_original_name,
                ['record_id' => $studentSanctionRecord->record_id]
            );
        }

        // Audit the access (owning student viewing their own proof of completion).
        try {
            app(\App\Services\EvidenceSecurityService::class)->logEvent(
                \App\Models\EvidenceSecurityEvent::PROOF_VIEWED,
                $studentSanctionRecord->violation_id,
                null,
                'info',
                $request,
                ['record_id' => $studentSanctionRecord->record_id],
                'student',
                (int) (Auth::user()?->user_id ?? Auth::user()?->getAuthIdentifier()),
            );
        } catch (\Throwable $e) {
            // Logging must never break the proof view.
        }

        return \App\Services\PrivateFileResponder::stream(
            'proof',
            $studentSanctionRecord->proof_path,
            $studentSanctionRecord->proof_original_name,
            ['record_id' => $studentSanctionRecord->record_id]
        );
    }

    /**
     * Serve a violation evidence file from private storage to the owning
     * student. Students may only open evidence once the violation has been
     * confirmed by an administrator (it is on their official record).
     *
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function downloadEvidence(ViolationEvidence $evidence, Request $request)
    {
        $violation = $evidence->violation;

        if (! $violation) {
            abort(404);
        }

        // Only the owning student may open their own evidence.
        $studentNumber = Auth::user()?->student_number;
        if ($studentNumber === null || (string) $violation->student_number !== (string) $studentNumber) {
            abort(403);
        }

        // Evidence is visible to the student only once the violation is on
        // their official record (confirmed). Pending/denied stay private.
        if ((string) $violation->status !== Violation::STATUS_CONFIRMED) {
            abort(403);
        }

        if (! $evidence->disk_path) {
            return \App\Services\PrivateFileResponder::stream(
                'evidence',
                null,
                $evidence->original_name,
                ['evidence_id' => $evidence->evidence_id ?? null]
            );
        }

        // Audit the access (owning student viewing their confirmed evidence).
        app(\App\Services\EvidenceSecurityService::class)->auditEvidenceAccess(
            'STUDENT_EVIDENCE_VIEWED',
            $evidence,
            'student',
            (int) (Auth::user()?->user_id ?? Auth::user()?->getAuthIdentifier()),
            $request,
        );

        return \App\Services\PrivateFileResponder::stream(
            'evidence',
            $evidence->disk_path,
            $evidence->original_name,
            ['evidence_id' => $evidence->evidence_id ?? null]
        );
    }
}