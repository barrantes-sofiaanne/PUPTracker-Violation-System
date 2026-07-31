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
            ->paginate(15, ['*'], 'requests_page');

        $studentSanctionsQuery = StudentSanctionRecord::with([
            'violation.violationType',
            'assignedSanction',
            'violation.student',
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
            ->paginate(15, ['*'], 'records_page');

        $requests->getCollection()->transform(function (SanctionRequest $request) {
            $offenseCount = Violation::where('student_number', $request->student_number)
                ->where('violation_type', $request->violation_type_id)
                ->count();

            $offenseLevel = $this->formatOffenseLevel($offenseCount);

            $latestViolation = Violation::where('student_number', $request->student_number)
                ->where('violation_type', $request->violation_type_id)
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

    public function approveRequest(Request $request, SanctionRequest $sanctionRequest): RedirectResponse
    {
        if (!$sanctionRequest->is_active) {
            return back()->withErrors([
                'sanction_request' => 'This sanction request has already been processed.',
            ]);
        }

        $validated = $request->validate([
            'notification_date' => ['required', 'date'],
        ]);

        $adminId = Auth::guard('admin')->id();
        $scheduledDate = Carbon::parse($validated['notification_date'])->startOfDay();

        DB::transaction(function () use ($sanctionRequest, $adminId, $scheduledDate): void {
            $student = $sanctionRequest->student;
            $violationType = $sanctionRequest->violationType;

            if (!$student || !$violationType) {
                throw ValidationException::withMessages([
                    'sanction_request' => 'This request cannot be processed because student or violation details are missing.',
                ]);
            }

            $latestViolation = Violation::where('student_number', $student->student_number)
                ->where('violation_type', $violationType->violation_type_id)
                ->latest('violation_date')
                ->first();

            if (!$latestViolation) {
                throw ValidationException::withMessages([
                    'sanction_request' => 'No matching violation record was found for this request.',
                ]);
            }

            $offenseCount = Violation::where('student_number', $student->student_number)
                ->where('violation_type', $violationType->violation_type_id)
                ->count();

            $offenseLevel = $this->formatOffenseLevel($offenseCount);

            $assignedSanction = DisciplinarySanction::where('violation_type_id', $violationType->violation_type_id)
                ->where('offense_level', $offenseLevel)
                ->first()
                ?? DisciplinarySanction::where('violation_type_id', $violationType->violation_type_id)
                    ->orderBy('disciplinary_sanction_id')
                    ->first();

            StudentSanctionRecord::create([
                'student_number' => $student->student_number,
                'violation_id' => $latestViolation->violation_id,
                'assigned_sanction_id' => $assignedSanction?->disciplinary_sanction_id,
                'assigned_by_admin_id' => $adminId,
                'status' => 'Pending',
                'date_assigned' => $scheduledDate,
            ]);

            $sanctionRequest->update([
                'is_active' => false,
                'status' => 'Approved',
                'approved_by_admin_id' => $adminId,
                'approved_at' => now(),
            ]);

            Notification::create([
                'student_number' => $student->student_number,
                'message' => 'Your sanction request was approved. Please check your Sanction Record tab. Scheduled date: ' . $scheduledDate->format('M d, Y') . '.',
                'is_read' => false,
                'link' => route('student.record'),
                'notification_type' => 'sanction',
                'recipient_type' => 'student',
            ]);

            AuditLog::create([
                'actor_type' => 'admin',
                'actor_id' => $adminId,
                'action' => 'Approve',
                'module' => 'Sanction Requests',
                'description' => 'Approved sanction request #' . $sanctionRequest->request_id . ' for student ' . $student->student_number . '.',
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
                Notification::create([
                    'student_number' => $student->student_number,
                    'message' => 'Your sanction request was declined by the administration.',
                    'is_read' => false,
                    'link' => route('student.record'),
                    'notification_type' => 'sanction',
                    'recipient_type' => 'student',
                ]);
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

    public function markRecordCompleted(StudentSanctionRecord $studentSanctionRecord): RedirectResponse
    {
        if (strcasecmp((string) $studentSanctionRecord->status, 'Pending') !== 0) {
            return back()->withErrors([
                'sanction_record' => 'Only pending sanctions can be marked as completed.',
            ]);
        }

        $adminId = Auth::guard('admin')->id();

        DB::transaction(function () use ($studentSanctionRecord, $adminId): void {
            $studentSanctionRecord->update([
                'status' => 'Completed',
                'date_completed' => now(),
            ]);

            Notification::create([
                'student_number' => $studentSanctionRecord->student_number,
                'message' => 'Your sanction status has been marked as completed by the administration.',
                'is_read' => false,
                'link' => route('student.record'),
                'notification_type' => 'sanction',
                'recipient_type' => 'student',
            ]);

            AuditLog::create([
                'actor_type' => 'admin',
                'actor_id' => $adminId,
                'action' => 'Complete',
                'module' => 'Sanction Records',
                'description' => 'Marked sanction record #' . $studentSanctionRecord->record_id . ' as completed.',
            ]);
        });

        return back()->with('success', 'Pending sanction marked as completed.');
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
            ]);

            Notification::create([
                'student_number' => $studentSanctionRecord->student_number,
                'message' => 'Your sanction status was reverted to pending by the administration.',
                'is_read' => false,
                'link' => route('student.record'),
                'notification_type' => 'sanction',
                'recipient_type' => 'student',
            ]);

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
