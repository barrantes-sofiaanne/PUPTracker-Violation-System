<?php

namespace App\Services;

use App\Models\AdminNotification;
use App\Models\EmailIncident;
use App\Models\SanctionRequest;
use App\Models\StudentSanctionRecord;
use App\Models\Violation;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class AdminNotificationService
{
    public function create(
        string $type,
        string $title,
        string $message,
        string $targetType,
        int $targetId,
        ?int $recipientId = null,
        ?string $recipientType = 'admin'
    ): AdminNotification {
        $data = [
            'message' => $message,
            'notification_type' => $type,
            'is_read' => false,
            'created_at' => now(),
        ];

        if (Schema::hasColumn('admin_notifications_tbl', 'title')) {
            $data['title'] = $title;
        }
        if (Schema::hasColumn('admin_notifications_tbl', 'target_type')) {
            $data['target_type'] = $targetType;
        }
        if (Schema::hasColumn('admin_notifications_tbl', 'target_id')) {
            $data['target_id'] = $targetId;
        }
        if (Schema::hasColumn('admin_notifications_tbl', 'recipient_type')) {
            $data['recipient_type'] = $recipientType;
        }
        if (Schema::hasColumn('admin_notifications_tbl', 'recipient_id')) {
            $data['recipient_id'] = $recipientId;
        }

        return AdminNotification::create($data);
    }

    public function openUrl(AdminNotification $notification): ?string
    {
        return match ($notification->target_type) {
            'sanction_request' => $this->sanctionRequestUrl((int) $notification->target_id),
            'email_incident' => $this->emailIncidentUrl((int) $notification->target_id),
            'violation' => $this->violationUrl((int) $notification->target_id),
            'sanction_record' => $this->sanctionRecordUrl((int) $notification->target_id),
            default => null,
        };
    }

    /**
     * Resolve a sanction-record (proof-of-completion) target to the Student
     * Sanctions tab where the admin reviews submitted proofs.
     */
    private function sanctionRecordUrl(int $targetId): ?string
    {
        if (! Route::has('admin.sanctions.index')) {
            return null;
        }

        $record = StudentSanctionRecord::query()->find($targetId);

        if (! $record) {
            return null;
        }

        return route('admin.sanctions.index', [
            'sanctions_tab' => 'student-sanctions',
            'record_id' => $record->record_id,
        ]);
    }

    /**
     * Resolve a violation-confirmation target to the admin confirmation queue.
     * When the specific violation is still awaiting review the admin lands on
     * the queue; otherwise (already reviewed or missing) they land on the
     * relevant student history so they can still inspect the record.
     */
    private function violationUrl(int $targetId): ?string
    {
        if (! Route::has('admin.violations.confirmations')) {
            return null;
        }

        $violation = Violation::query()->find($targetId);

        if (! $violation) {
            return null;
        }

        $route = route('admin.violations.confirmations');

        // If the violation is no longer pending, drop the admin on the student
        // history where the row is visible.
        if (strtolower((string) $violation->status) !== 'pending') {
            if (Route::has('admin.violations.show')) {
                return route('admin.violations.show', [
                    'student_number' => $violation->student_number,
                    'source' => 'confirmations',
                ]);
            }

            return $route;
        }

        return $route . '?pending_id=' . $targetId;
    }

    /**
     * Resolve an admin sanction-request target to its exact review page.
     *
     * The admin must remain in the Admin module and land on the specific
     * sanction request (never the generic list, student route, or student
     * login). Returns null when the record no longer exists or the route is
     * not available, in which case the caller reports the record unavailable.
     */
    private function sanctionRequestUrl(int $targetId): ?string
    {
        if (! Route::has('admin.sanctions.requests.show')) {
            return null;
        }

        $request = SanctionRequest::query()->find($targetId);

        if (! $request) {
            return null;
        }

        return route('admin.sanctions.requests.show', $request->request_id);
    }

    /**
     * Resolve an email-incident target to its super-admin detail page.
     */
    private function emailIncidentUrl(int $targetId): ?string
    {
        if (! Route::has('admin.super-admin.email-incidents.show')) {
            return null;
        }

        $incident = EmailIncident::query()->find($targetId);

        if (! $incident) {
            return null;
        }

        return route('admin.super-admin.email-incidents.show', $incident->incident_id);
    }
}