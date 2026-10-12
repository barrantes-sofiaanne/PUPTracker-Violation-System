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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

// Latest request for each violation type drives the status shown in the log.
$sanctionRequestsByType = SanctionRequest::where('student_number', $user->student_number)
    ->orderByDesc('request_date')
    ->get()
    ->unique('violation_type_id')
    ->keyBy(fn ($request) => (string) $request->violation_type_id);

// Build filter options only from this student's confirmed violations.
$studentRecordedViolations = Violation::with('violationType.violationCategory')
    ->where('student_number', $user->student_number)
    ->where('status', Violation::STATUS_CONFIRMED)
    ->get();

$recordedViolationTypes = $studentRecordedViolations
    ->map(fn ($violation) => $violation->resolvedViolationType())
    ->filter()
    ->unique('violation_type_id')
    ->sortBy('violation_type')
    ->values();

$recordedViolationCategories = $recordedViolationTypes
    ->map(fn ($type) => $type->violationCategory)
    ->filter()
    ->unique('violation_category_id')
    ->sortBy('category_name')
    ->values();

$filters = request()->validate([
    'category_id' => 'nullable|integer',
    'violation_type_id' => 'nullable|integer',
    'date_from' => 'nullable|date',
    'date_to' => 'nullable|date|after_or_equal:date_from',
]);

// Identify confirmed individual violations whose applicable configured
// sanction is a warning. These records stay in the Individual Violations Log
// and should not offer a Request Sanction action.
$confirmedViolationsForSanctionCheck = Violation::with([
    'violationType.disciplinarySanctions',
])
    ->where('student_number', $user->student_number)
    ->effective()
    ->get();

$confirmedTypeCounts = $confirmedViolationsForSanctionCheck
    ->groupBy(function ($record) {
        $resolvedType = $record->resolvedViolationType();
        return (string) ($resolvedType?->violation_type_id ?? 'unknown:' . $record->violation_id);
    });

$warningLevelViolationIds = [];
$relatedDisciplinaryConsequences = [];
foreach ($confirmedTypeCounts as $recordsForType) {
    $firstRecord = $recordsForType->first();
    $resolvedType = $firstRecord?->resolvedViolationType();

    if (!$resolvedType) {
        continue;
    }

    $count = $recordsForType->count();
    $offenseLevel = match ($count) {
        1 => '1st Offense',
        2 => '2nd Offense',
        3 => '3rd Offense',
        default => $count . 'th Offense',
    };

    $configuredSanctions = $resolvedType->disciplinarySanctions ?? collect();
    $applicableSanction = $configuredSanctions->firstWhere('offense_level', $offenseLevel)
        ?? $configuredSanctions->sortBy('disciplinary_sanction_id')->first();

    if ($applicableSanction && str_contains(
        strtolower((string) $applicableSanction->disciplinary_sanction),
        'warning'
    )) {
        foreach ($recordsForType as $warningRecord) {
            $warningLevelViolationIds[(int) $warningRecord->violation_id] = true;

            $relatedDisciplinaryConsequences[] = [
                'violation_id' => (int) $warningRecord->violation_id,
                'violation_type' => $warningRecord->violation_type_display
                    ?? $resolvedType->violation_type
                    ?? 'Recorded violation',
                'category' => $warningRecord->violation_category_display
                    ?? $resolvedType->violationCategory?->category_name
                    ?? '—',
                'violation_date' => $warningRecord->violation_date,
                'offense_level' => $offenseLevel,
                'disciplinary_sanction' => (string) $applicableSanction->disciplinary_sanction,
            ];
        }
    }
}

$relatedDisciplinaryConsequences = collect($relatedDisciplinaryConsequences)
    ->sortByDesc('violation_date')
    ->values();

        $violationsQuery = Violation::with([
            'violationType.violationCategory',
            'violationType.disciplinarySanctions',
            'evidence',
        ])
            ->where('student_number', $user->student_number)
            // Only administrator-confirmed records are visible to students.
            ->where('status', Violation::STATUS_CONFIRMED);

        if (!empty($filters['category_id'])) {
            $violationsQuery->whereHas('violationType', function ($query) use ($filters) {
                $query->where('violation_category_id', $filters['category_id']);
            });
        }

        if (!empty($filters['violation_type_id'])) {
            $violationsQuery->where('violation_type_id', $filters['violation_type_id']);
        }

        if (!empty($filters['date_from'])) {
            $violationsQuery->whereDate('violation_date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $violationsQuery->whereDate('violation_date', '<=', $filters['date_to']);
        }

        $violations = $violationsQuery
            ->orderByDesc('violation_date')
            ->paginate(10)
            ->withQueryString();
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

// Group ONLY administrator-confirmed violations by violation type.
// The Student Record must never expose a pending/unconfirmed violation.
// Warning-only violations, or violation types that already entered the
// sanction workflow, are removed from the Summary table.
$groupedViolations = Violation::with([
        'violationType.violationCategory',
        'violationType.disciplinarySanctions',
        'evidence',
    ])
    ->where('student_number', $user->student_number)
    // Only Administrator-confirmed violations are eligible for the
    // student-facing Summary by Violation. Pending/denied/unconfirmed
    // records must remain invisible to the student.
    ->where('status', Violation::STATUS_CONFIRMED)
    ->get()
    ->groupBy(function ($violation) {
        $resolvedType = $violation->resolvedViolationType();

        if (!empty($resolvedType?->violation_type_id)) {
            return 'id:' . $resolvedType->violation_type_id;
        }

return 'unknown:' . $violation->violation_id;    });

foreach ($groupedViolations as $records) {

    // Keep the newest effective incident first so Summary actions point to a concrete record.
    $records = collect($records)->sortByDesc('violation_date')->values();

    // All records in this collection are Administrator-confirmed, so the
    // offense level is computed only from records the student is allowed to see.
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

    // There are no pending records in this student-facing collection.
    // Every Summary row therefore represents at least one confirmed violation.
    $first = $effectiveRecords->first();
    $count = $effectiveRecords->count();
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

    // Warning-only violations remain visible in the summary with Warning status,
    // but the Individual Violations Log replaces the request button with guidance.

    if (
        $matchesViolationType($first, $approvedRequests)
        || $matchesViolationType($first, $assignedSanctionTypeKeys)
    ) {
        // A violation type that already entered the sanction workflow (open
        // OR completed) belongs to the Sanction Record tab only. Completed
        // sanctions are therefore removed from the Summary table.
        continue;
    }

    // At this point the violation is confirmed and has a disciplinary
    // sanction, so the student may see the Summary row.
    $workflowStatus = $matchesViolationType($first, $activeRequests)
        ? 'Requested'
        : 'Actionable';

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
        'correction_violation_category_id' => $correctionViolation?->resolvedViolationType()?->violation_category_id,
        'correction_violation_category' => $correctionViolation?->violation_category_display,
        'correction_violation_offense_level' => $offenseLevel,
        'correction_violation_sanction' => $disciplinarySanction,
        'evidence' => collect($correctionViolation?->evidence ?? [])
            ->filter(fn ($item) => $correctionViolation
                && (string) $correctionViolation->status === Violation::STATUS_CONFIRMED
                && !empty($item->access_key))
            ->map(fn ($item) => [
                'access_key' => $item->access_key,
                'original_name' => $item->original_name ?: 'Evidence',
                'mime_type' => $item->mime_type,
                'is_image' => $item->is_image,
            ])
            ->values()
            ->all(),

        'offense_level' => $offenseLevel,

        'remarks' => $hasPending
            ? ($hasEffective ? '(New record awaiting confirmation)' : ($first->description ?: 'Awaiting confirmation'))
            : ($count > 1 ? '(Multiple instances - see log)' : ($first->description ?: 'No remarks')),

        'disciplinary_sanction' => $disciplinarySanction,

        // Only sanctions that require administration scheduling (Community or
        // Campus Service) expose Request Sanction in the student UI.
        'requires_sanction_request' => StudentSanctionRecord::requiresStudentSanctionRequest($disciplinarySanction),

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
        'assignedSanctionViolationIds',
        'sanctionRequestsByType',
        'recordedViolationTypes',
        'recordedViolationCategories',
        'filters',
        'warningLevelViolationIds',
        'relatedDisciplinaryConsequences'
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
    if (! $user instanceof \App\Models\User || ! $this->isStudentAccountActive($user)) {
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
    ->where('violation_type_id', $violationType->violation_type_id)
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

    // Allow a student to request sanction review for any configured,
    // non-warning sanction. The consultation notice remains in the Sanction
    // Record tab for sanctions that do not require proof of completion; it
    // must not block creation of the sanction request itself.

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
    ->where('violation_type_id', $violationType->violation_type_id)
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

/**
 * Return authoritative Category -> Violation Type -> Offense Level ->
 * Disciplinary Sanction data for the correction modal.
 */
public function correctionOptions(Request $request, Violation $violation)
{
    $user = Auth::user();

    abort_unless(
        $user instanceof User
            && (string) $violation->student_number === (string) $user->student_number
            && $violation->is_effective,
        403
    );

    $validated = $request->validate([
        'violation_type_id' => ['required', 'integer', 'exists:violation_type_tbl,violation_type_id'],
    ]);

    $type = ViolationType::with(['violationCategory', 'disciplinarySanctions'])
        ->findOrFail((int) $validated['violation_type_id']);

    abort_unless($type->violationCategory !== null, 422, 'The selected violation type has no category.');

    $effectiveCount = Violation::query()
        ->where('student_number', $user->student_number)
        ->where('violation_type_id', $type->violation_type_id)
        ->where('violation_id', '!=', $violation->violation_id)
        ->notDenied()
        ->where(function ($query) {
            $query->whereNull('status')
                ->orWhere('status', '!=', Violation::STATUS_PENDING);
        })
        ->count() + 1;

    $offenseLevel = match ($effectiveCount) {
        1 => '1st Offense',
        2 => '2nd Offense',
        3 => '3rd Offense',
        default => $effectiveCount . 'th Offense',
    };

    $sanction = $type->disciplinarySanctions
        ->firstWhere('offense_level', $offenseLevel)
        ?? $type->disciplinarySanctions->sortBy('disciplinary_sanction_id')->first();

    return response()->json([
        'category_id' => $type->violationCategory->violation_category_id,
        'category' => $type->violationCategory->category_name,
        'violation_type_id' => $type->violation_type_id,
        'violation_type' => $type->violation_type,
        'offense_level' => $offenseLevel,
        'disciplinary_sanction' => $sanction?->disciplinary_sanction ?? 'N/A',
    ]);
}


    /** Submit a deletion request for an individual confirmed violation. */
    public function requestDeletion(Request $request, Violation $violation)
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);
        abort_unless((string) $violation->student_number === (string) $user->student_number, 403);
        abort_unless($this->isStudentAccountActive($user), 403, 'Your account is inactive.');
        abort_unless($violation->is_effective, 422, 'Only confirmed violations can be submitted for deletion review.');

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
            'evidence_files' => ['nullable', 'array', 'max:5'],
            'evidence_files.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf'],
            'camera_image' => ['nullable', 'string', 'max:12000000'],
        ]);

        $pending = ViolationCorrectionRequest::where('violation_id', $violation->violation_id)
            ->where('student_number', $user->student_number)
            ->where('status', ViolationCorrectionRequest::STATUS_PENDING)->exists();
        if ($pending) {
            return back()->withErrors(['correction_request' => 'A change request for this violation is already pending review.']);
        }

        $files = [];
        try {
            foreach ($request->file('evidence_files', []) as $file) {
                $path = $file->store('private/violation-change-requests/' . $violation->violation_id, 'local');
                $files[] = ['path' => $path, 'name' => $file->getClientOriginalName(), 'mime' => $file->getMimeType()];
            }
            if (!empty($validated['camera_image'])) {
                if (!preg_match('/^data:image\/(png|jpeg|webp);base64,/', $validated['camera_image'], $matches)) {
                    throw new \RuntimeException('The camera image format is invalid.');
                }
                $raw = substr($validated['camera_image'], strpos($validated['camera_image'], ',') + 1);
                $binary = base64_decode($raw, true);
                if ($binary === false || strlen($binary) > 8 * 1024 * 1024) {
                    throw new \RuntimeException('The captured image is invalid or too large.');
                }
                $extension = $matches[1] === 'jpeg' ? 'jpg' : $matches[1];
                $path = 'private/violation-change-requests/' . $violation->violation_id . '/' . Str::uuid() . '.' . $extension;
                Storage::disk('local')->put($path, $binary);
                $files[] = ['path' => $path, 'name' => 'Camera evidence.' . $extension, 'mime' => 'image/' . $matches[1]];
            }

            $changeRequest = ViolationCorrectionRequest::create([
                'violation_id' => $violation->violation_id,
                'student_number' => $user->student_number,
                'request_type' => ViolationCorrectionRequest::TYPE_DELETION,
                'reason' => trim($validated['reason']),
                'deletion_evidence' => $files,
                'status' => ViolationCorrectionRequest::STATUS_PENDING,
            ]);

            try {
                app(AdminNotificationService::class)->create(
                    type: 'violation_deletion_request',
                    title: 'Violation Deletion Request',
                    message: $user->first_name . ' ' . $user->last_name . ' (' . $user->student_number . ') submitted a deletion request for violation #' . $violation->violation_id . '.',
                    targetType: 'violation_correction_request',
                    targetId: (int) $changeRequest->request_id,
                );
            } catch (\Throwable $e) {
                Log::warning('Unable to send admin notification for deletion request.', ['error' => $e->getMessage()]);
            }

            return redirect()->route('student.record')->with('success', 'Your deletion request and supporting evidence were submitted to the Administrator for review.');
        } catch (\Throwable $e) {
            foreach ($files as $file) Storage::disk('local')->delete($file['path']);
            Log::error('Student deletion request failed.', ['violation_id' => $violation->violation_id, 'error' => $e->getMessage()]);
            return back()->withErrors(['correction_request' => 'The deletion request could not be submitted. Please try again.']);
        }
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
    if (! $this->isStudentAccountActive($user)) {
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

        'requested_violation_category_id' => [
            'required',
            'integer',
            'exists:violation_category_tbl,violation_category_id',
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

    $requestedType = ViolationType::with('violationCategory')
        ->findOrFail((int) $validated['requested_violation_type_id']);

    if (
        ! $requestedType->violationCategory
        || (int) $requestedType->violationCategory->violation_category_id
            !== (int) $validated['requested_violation_category_id']
    ) {
        return back()->withErrors([
            'correction_request' =>
                'The selected violation type does not belong to the selected category.',
        ])->withInput();
    }

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
     * Notify the Administrator.
     *
     * The correction queue is reviewed by the Administrator.
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
                'Your violation correction request has been submitted and is now pending review by the Administrator.',
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
        'Your correction request has been submitted. The Administrator will review it.'
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

    private function isStudentAccountActive(User $user): bool
    {
        $active = $user->getAttribute('is_active');

        if ($active !== null) {
            return (bool) $active;
        }

        $status = $user->getAttribute('account_status') ?? $user->getAttribute('status');

        return $status === null
            || ! in_array(strtolower(trim((string) $status)), ['inactive', 'disabled', 'deactivated'], true);
    }
}
