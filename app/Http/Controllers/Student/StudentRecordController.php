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
        $raw = (string) ($record->violation?->violation_type ?? '');
        $resolvedTypeId = (string) ($record->violation?->resolvedViolationType()?->violation_type_id ?? '');
        $resolvedTypeName = (string) ($record->violation?->resolvedViolationType()?->violation_type ?? '');

        return array_filter([
            $raw,
            strtolower($raw),
            $resolvedTypeId,
            $resolvedTypeName,
            strtolower($resolvedTypeName),
        ]);
    })
    ->unique()
    ->values()
    ->toArray();

$matchesViolationType = function ($violationRecord, array $pool): bool {
    $rawType = (string) ($violationRecord->violation_type ?? '');
    $relationTypeId = (string) ($violationRecord->violationType?->violation_type_id ?? '');
    $relationTypeName = (string) ($violationRecord->violationType?->violation_type ?? '');
    $resolvedType = $violationRecord->resolvedViolationType();
    $resolvedTypeId = (string) ($resolvedType?->violation_type_id ?? '');
    $resolvedTypeName = (string) ($resolvedType?->violation_type ?? '');

    $candidates = array_filter([
        $rawType,
        strtolower($rawType),
        $relationTypeId,
        $relationTypeName,
        strtolower($relationTypeName),
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

        return 'raw:' . strtolower(trim((string) $violation->violation_type));
    });

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
    'sanctionRecords'
)
        );
    }

public function requestSanction(Request $request)
{
    $request->validate([
        'violation_type_id' => 'required|integer'
    ]);

    /** @var \App\Models\User $user */
    $user = Auth::user();

    /*
     * Inactive students cannot create new sanction requests.
     * This is enforced server-side even if the browser UI is bypassed.
     */
    if (! $user instanceof \App\Models\User || ! $user->isActiveAccount()) {
        return response()->json([
            'success' => false,
            'message' => 'A sanction request cannot be created because your PUPTracker account is inactive.',
        ], 403);
    }

    $violationType = \App\Models\ViolationType::find($request->violation_type_id);

    if (!$violationType) {
        return response()->json([
            'success' => false,
            'message' => 'Violation type was not found.'
        ], 422);
    }

    $offenseCount = Violation::where('student_number', $user->student_number)
        ->where(function ($query) use ($violationType) {
            $query->where('violation_type', (string) $violationType->violation_type_id)
                ->orWhere('violation_type', $violationType->violation_type);
        })
        ->effective()
        ->count();

    if ($offenseCount < 1) {
        return response()->json([
            'success' => false,
            'message' => 'No matching violation record found for this request.'
        ], 422);
    }

    $offenseLevel = match ($offenseCount) {
        1 => '1st Offense',
        2 => '2nd Offense',
        3 => '3rd Offense',
        default => $offenseCount . 'th Offense',
    };

    $assignedSanction = DisciplinarySanction::where('violation_type_id', $violationType->violation_type_id)
        ->where('offense_level', $offenseLevel)
        ->first()
        ?? DisciplinarySanction::where('violation_type_id', $violationType->violation_type_id)
            ->orderBy('disciplinary_sanction_id')
            ->first();

    if (!$assignedSanction) {
        return response()->json([
            'success' => false,
            'message' => 'No disciplinary sanction is configured for this violation yet.'
        ], 422);
    }

    $sanctionText = strtolower((string) ($assignedSanction?->disciplinary_sanction ?? ''));
    if ($sanctionText !== '' && str_contains($sanctionText, 'warning')) {
        return response()->json([
            'success' => false,
            'message' => 'Warning-level violations remain in the violation log and cannot be moved to sanction records.'
        ], 422);
    }

    $existing = SanctionRequest::where(
        'student_number',
        $user->student_number
    )
    ->where(
        'violation_type_id',
        $request->violation_type_id
    )
    ->where(
        'is_active',
        1
    )
    ->first();

    if ($existing) {

        return response()->json([

            'success' => false,

            'message' => 'You already have an active request.'

        ]);

    }

    DB::beginTransaction();

    try {

        $sanctionRequest = SanctionRequest::create([

            'student_number' => $user->student_number,

            'violation_type_id' => $request->violation_type_id,

            'request_date' => now(),

            'is_active' => true,

            'status' => 'Pending',

            'approved_by_admin_id' => null,

            'approved_at' => null,

        ]);

        // Validate the student has a matching confirmed violation before
        // queueing the request. The actual StudentSanctionRecord is NOT created
        // here — it is created only after an administrator approves the request
        // (with the authenticated admin id and deadline). This keeps pending
        // requests out of the sanction tab / graduation screens until approved.
        $latestViolation = Violation::where('student_number', $user->student_number)
            ->where(function ($query) use ($violationType) {
                $query->where('violation_type', (string) $violationType->violation_type_id)
                    ->orWhere('violation_type', $violationType->violation_type);
            })
            ->effective()
            ->latest('violation_date')
            ->first();

        if (!$latestViolation) {
            throw new \RuntimeException('Unable to locate the latest violation for this sanction request.');
        }

        app(AdminNotificationService::class)->create(
            type: 'disciplinary_sanction_request',
            title: 'Disciplinary Sanction Request',
            message: $user->first_name . ' ' . $user->last_name . ' requested a disciplinary sanction.',
            targetType: 'sanction_request',
            targetId: (int) $sanctionRequest->request_id,
        );

        // Notify the student that their request is now pending (target-aware).
        try {
            app(\App\Services\StudentNotificationService::class)->create(
                studentNumber: $user->student_number,
                type: 'sanction',
                title: 'Sanction Request Pending',
                message: 'Your sanction request is now pending review. You will be notified once it is approved or declined.',
                targetType: 'sanction_request',
                targetId: (int) $sanctionRequest->request_id,
            );
        } catch (\Throwable $e) {
            // Notification must never break the request submission.
        }

        DB::commit();

        return response()->json([

            'success' => true,

            'message' => 'Request submitted successfully. It is now pending review by the administration.',

            'request_id' => $sanctionRequest->request_id,

        ]);

    } catch (\Exception $e) {

        DB::rollBack();

        Log::error('Student sanction request failed', [
            'student_number' => $user->student_number ?? null,
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);

        return \App\Support\Responses::unexpectedErrorFlag();

    }
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