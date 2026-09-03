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
           ->effective()
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

$pendingSanctionTypeKeys = StudentSanctionRecord::with('violation')
    ->where('student_number', $user->student_number)
    ->where(function ($q) {
        $q->where('status', \App\Models\StudentSanctionRecord::STATUS_PENDING)
            ->orWhere('status', \App\Models\StudentSanctionRecord::STATUS_IN_PROGRESS)
            ->orWhere('status', \App\Models\StudentSanctionRecord::STATUS_OVERDUE);
    })
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

$approvedSanctionTypeKeys = StudentSanctionRecord::with('violation')
    ->where('student_number', $user->student_number)
    ->where(function ($q) {
        $q->where('status', '!=', \App\Models\StudentSanctionRecord::STATUS_COMPLETED);
    })
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

$groupedViolations = Violation::with([
        'violationType.violationCategory',
    'violationType.disciplinarySanctions'
])
    ->where('student_number', $user->student_number)
    ->effective()
    ->get()
    ->groupBy(function ($violation) {
        $resolvedType = $violation->resolvedViolationType();

        if (!empty($resolvedType?->violation_type_id)) {
            return 'id:' . $resolvedType->violation_type_id;
        }

        return 'raw:' . strtolower(trim((string) $violation->violation_type));
    });

foreach ($groupedViolations as $records) {

    $first = $records->first();
    $count = $records->count();

    $offenseLevel = match ($count) {
        1 => '1st Offense',
        2 => '2nd Offense',
        3 => '3rd Offense',
        default => $count . 'th Offense'
    };

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

    if (
        $matchesViolationType($first, $pendingSanctionTypeKeys)
        || $matchesViolationType($first, $approvedRequests)
        || $matchesViolationType($first, $approvedSanctionTypeKeys)
    ) {
        // Once a violation enters sanction workflow, it should be shown only in Sanction Record.
        continue;
    }

    $workflowStatus = $matchesViolationType($first, $activeRequests)
        ? 'Requested'
        : 'Actionable';

    $violationSummary[] = [
        // Using ?-> prevents the "reading property on null" error
        'category' => $first->violation_category_display,

        'type' => $first->violation_type_display,

        'violation_type_id' => $resolvedType?->violation_type_id,

        'offense_level' => $offenseLevel,

        'remarks' => $count > 1
            ? '(Multiple instances - see log)'
            : ($first->description ?: 'No remarks'),

        'disciplinary_sanction' => $disciplinarySanction,

        'violation_status' => $status,

        'workflow_status' => $workflowStatus
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

    $user = Auth::user();

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

        return response()->json([

            'success' => false,

            'message' => $e->getMessage()

        ], 500);

    }
}

    /**
     * Submit a proof of completion for a pending disciplinary sanction.
     *
     * The uploaded file and optional notes are attached to the sanction record
     * and an administrator is notified so they can accept or reject the proof.
     */
    public function submitProof(Request $request)
    {
        $request->validate([
            'record_id' => ['required', 'integer'],
            'proof_file' => ['required', 'file', 'mimes:jpg,jpeg,png,gif,pdf,doc,docx', 'max:5120'],
            'proof_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        /** @var User $user */
        $user = Auth::user();

        $record = StudentSanctionRecord::with('violation')
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

        try {
            $file = $request->file('proof_file');
            $disk = \Illuminate\Support\Facades\Storage::disk('local'); // private storage
            $directory = 'sanction-proofs/' . $record->record_id;

            $extension = strtolower($file->getClientOriginalExtension());
            $filename = 'proof_' . $record->record_id . '_' . now()->format('Ymd_His') . '_' . uniqid('', true) . '.' . $extension;
            $storedPath = $disk->putFileAs($directory, $file, $filename);

            if ($storedPath === false) {
                throw new \RuntimeException('Unable to store proof file.');
            }

            $notes = trim((string) $request->input('proof_notes', ''));

            // If the record was never assigned (still Pending), submitting a
            // proof implies it is now active/in progress.
            if (strcasecmp((string) $record->status, \App\Models\StudentSanctionRecord::STATUS_PENDING) === 0) {
                $record->status = \App\Models\StudentSanctionRecord::STATUS_IN_PROGRESS;
            }

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

            // Preserve per-submission proof history.
            try {
                \App\Models\SanctionProof::create([
                    'record_id' => $record->record_id,
                    'disk_path' => $storedPath,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'notes' => $notes,
                    'status' => \App\Models\SanctionProof::STATUS_SUBMITTED,
                    'submitted_at' => now(),
                ]);
            } catch (\Throwable $e) {
                // A proof-history write failure must not fail the submission.
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
    public function downloadProof(StudentSanctionRecord $studentSanctionRecord)
    {
        if ((string) $studentSanctionRecord->student_number !== (string) Auth::user()->student_number) {
            abort(403);
        }

        if ($studentSanctionRecord->proof_path === null || $studentSanctionRecord->proof_path === '') {
            abort(404);
        }

        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = \Illuminate\Support\Facades\Storage::disk('local');

        if (! $disk->exists($studentSanctionRecord->proof_path)) {
            abort(404);
        }

        return $disk->response(
            $studentSanctionRecord->proof_path,
            $studentSanctionRecord->proof_original_name ?: basename($studentSanctionRecord->proof_path)
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

        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = \Illuminate\Support\Facades\Storage::disk((string) config('filesystems.evidence_disk', 'local'));

        if (! $evidence->disk_path || ! $disk->exists($evidence->disk_path)) {
            abort(404);
        }

        // Audit the access (owning student viewing their confirmed evidence).
        app(\App\Services\EvidenceSecurityService::class)->auditEvidenceAccess(
            'EVIDENCE_VIEWED',
            $evidence,
            'student',
            (int) (Auth::user()?->user_id ?? Auth::user()?->getAuthIdentifier()),
            $request,
        );

        return $disk->response(
            $evidence->disk_path,
            $evidence->original_name ?: basename($evidence->disk_path)
        );
    }
}