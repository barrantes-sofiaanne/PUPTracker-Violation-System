<?php

namespace App\Services;

use App\Mail\SystemNotification;
use App\Models\Admin;
use App\Models\AdminNotification;
use App\Models\EmailIncident;
use App\Models\SanctionRequest;
use App\Models\StudentSanctionRecord;
use App\Models\Violation;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class AdminNotificationService
{
    /**
     * Create an admin notification.
     *
     * AUDIENCE IS NOT DERIVED FROM ROLE. The caller states who the notification
     * is FOR:
     *   - default            -> AUDIENCE_ADMIN (operational admins). Super Admins
     *                           are excluded, so they never inherit student or
     *                           personnel notifications.
     *   - AUDIENCE_SUPER_ADMIN -> the Super Admin inbox (system-level only).
     *
     * Pass `$recipientId` to address one specific account instead of the whole
     * audience.
     *
     * @param  string  $recipientType  AdminNotification::AUDIENCE_* constant.
     * @param  string  $priority       AdminNotification::PRIORITY_* constant.
     */
    public function create(
        string $type,
        string $title,
        string $message,
        string $targetType,
        int $targetId,
        ?int $recipientId = null,
        ?string $recipientType = AdminNotification::AUDIENCE_ADMIN,
        string $priority = AdminNotification::PRIORITY_INFO
    ): AdminNotification {
        $data = [
            'message' => $message,
            'notification_type' => $type,
            'is_read' => false,
            'created_at' => now(),
        ];

        // Never let a missing/blank audience silently broaden the recipient set.
        $audience = ($recipientType === null || trim($recipientType) === '')
            ? AdminNotification::AUDIENCE_ADMIN
            : $recipientType;

        if (Schema::hasColumn('admin_notifications_tbl', 'title')) {
            $data['title'] = $title;
        }
        if (Schema::hasColumn('admin_notifications_tbl', 'priority')) {
            $data['priority'] = $priority;
        }
        if (Schema::hasColumn('admin_notifications_tbl', 'target_type')) {
            $data['target_type'] = $targetType;
        }
        if (Schema::hasColumn('admin_notifications_tbl', 'target_id')) {
            $data['target_id'] = $targetId;
        }
        if (Schema::hasColumn('admin_notifications_tbl', 'recipient_type')) {
            $data['recipient_type'] = $audience;
        }
        if (Schema::hasColumn('admin_notifications_tbl', 'recipient_id')) {
            $data['recipient_id'] = $recipientId;
        }

        $notification = AdminNotification::create($data);

// Email only user-facing operational notifications.
// System alerts are intentionally excluded for now because
// EmailIncidentService creates system_email_failure notifications
// when an email itself fails.
// Email the operational Admins when a violation is awaiting confirmation.
if (
    $audience === AdminNotification::AUDIENCE_ADMIN
    && $type === 'violation_confirmation'
) {
    try {
        $this->sendViolationConfirmationEmail($notification);
    } catch (\Throwable $e) {
        Log::warning('Failed to send violation notification email', [
            'notification_id' => $notification->admin_notification_id,
            'error' => $e->getMessage(),
        ]);
    }
}
// Email the operational Admins when a student submits a sanction request.
if (
    $audience === AdminNotification::AUDIENCE_ADMIN
    && $type === 'sanction_request'
    && $targetType === 'sanction_request'
) {
    try {
        $this->sendSanctionRequestEmail($notification);
    } catch (\Throwable $e) {
        Log::warning('Failed to send sanction request notification email', [
            'notification_id' => $notification->admin_notification_id,
            'error' => $e->getMessage(),
        ]);
    }
}
// Email the specific OSS Staff member when their violation is confirmed/rejected.
// Email the specific OSS Staff member when their violation is confirmed or rejected.
if (
    $audience === AdminNotification::AUDIENCE_ADMIN
    && $type === 'violation'
    && $targetType === 'oss_staff_violation'
    && $recipientId !== null
) {
    try {
        $this->sendOssStaffDecisionEmail($notification);
    } catch (\Throwable $e) {
        Log::warning('Failed to send OSS Staff decision email', [
            'notification_id' => $notification->admin_notification_id,
            'error' => $e->getMessage(),
        ]);
    }
}

return $notification;
    }

    /**
     * Create a SYSTEM-LEVEL notification for the Super Admin (§3/§5).
     *
     * Only backup / infrastructure / storage / critical-security events belong
     * here. Student-specific and personnel-specific events must NOT use this
     * method — they go through create() with the default admin audience.
     */
    public function createSystemAlert(
        string $type,
        string $title,
        string $message,
        string $targetType,
        int $targetId,
        string $priority = AdminNotification::PRIORITY_CRITICAL,
        ?int $recipientId = null
    ): AdminNotification {
        return $this->create(
            type: $type,
            title: $title,
            message: $message,
            targetType: $targetType,
            targetId: $targetId,
            recipientId: $recipientId,
            recipientType: AdminNotification::AUDIENCE_SUPER_ADMIN,
            priority: $priority,
        );
    }

    public function openUrl(AdminNotification $notification): ?string
    {
        return match ($notification->target_type) {
            'sanction_request' => $this->sanctionRequestUrl((int) $notification->target_id),
            'email_incident' => $this->emailIncidentUrl((int) $notification->target_id),
            'violation' => $this->violationUrl((int) $notification->target_id),
            'oss_staff_violation' => $this->ossStaffViolationUrl((int) $notification->target_id),
            'backup' => $this->backupUrl((int) $notification->target_id),
            'sanction_record' => $this->sanctionRecordUrl((int) $notification->target_id),
            default => null,
        };
    }

    /**
     * Resolve an OSS-Staff violation target to the OSS Staff module.
     *
     * An OSS Staff member who recorded a violation must land inside THEIR OWN
     * module (never the Admin confirmation queue or the Security tracker):
     *   - CONFIRMED -> the student record page, highlighting the violation.
     *   - otherwise -> their Submitted Violations tracker, same highlight.
     */
    private function ossStaffViolationUrl(int $targetId): ?string
    {
        if (! Route::has('oss-staff.violations.students')) {
            return null;
        }

        $violation = Violation::query()->find($targetId);

        if (! $violation) {
            return null;
        }

        if (strtolower((string) $violation->status) === Violation::STATUS_CONFIRMED
            && Route::has('oss-staff.violations.show')) {
            return route('oss-staff.violations.show', [
                'student_number' => $violation->student_number,
                'highlight_violation' => $violation->violation_id,
            ]);
        }

        return route('oss-staff.violations.students', [
            'tab' => 'submitted',
            'highlight_violation' => $violation->violation_id,
        ]);
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
     * Resolve a backup alert to the Backup Management page (§6/§7).
     *
     * Lands on the specific backup record when it still exists so the Super
     * Admin sees the failure reason immediately; otherwise falls back to the
     * backup history list.
     */
    private function backupUrl(int $targetId): ?string
    {
        if (! Route::has('admin.super-admin.backups.show')) {
            return Route::has('admin.super-admin.backups.index')
                ? route('admin.super-admin.backups.index')
                : null;
        }

        $backup = \App\Models\BackupHistory::query()->find($targetId);

        if (! $backup) {
            return Route::has('admin.super-admin.backups.index')
                ? route('admin.super-admin.backups.index')
                : null;
        }

        return route('admin.super-admin.backups.show', $backup->id);
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
    /**
 * Send the email corresponding to an operational violation-confirmation
 * notification.
 *
 * The email recipient follows the exact same audience rules as the
 * AdminNotification inbox:
 *
 * - recipient_id provided -> that specific operational admin
 * - recipient_id null -> all operational admins
 *
 * Super Admins are excluded from operational notifications.
 */
private function sendViolationConfirmationEmail(AdminNotification $notification): void
{
$admins = Admin::query()
    ->with('adminInfo')
    ->get()
    ->filter(function (Admin $admin): bool {
        // Operational Admins receive violation confirmation emails.
        // IT Administrator = Super Admin.
        // OSS Staff must not receive Admin-only violation confirmation emails.
        return ! $admin->isItAdministrator()
            && ! $admin->isOssStaff();
    });

    // If this notification was addressed to one specific admin,
    // only email that administrator.
    if ($notification->recipient_id !== null) {
        $admins = $admins->filter(
            fn (Admin $admin): bool =>
                (int) $admin->id === (int) $notification->recipient_id
        );
    }

    foreach ($admins as $admin) {
        $email = trim((string) $admin->email);

        if ($email === '') {
            continue;
        }

        try {
            Mail::to($email)->send(
                new SystemNotification(
                    notificationTitle: (string) ($notification->title ?? 'Violation Awaiting Confirmation'),
                    notificationMessage: (string) $notification->message,
                    recipientName: (string) ($admin->username ?: 'Administrator'),
                    actionUrl: $this->openUrl($notification),
                    actionText: 'View Notification',
                )
            );
        } catch (\Throwable $e) {
            // Record the failure without allowing the original violation
            // operation to fail.
            app(EmailIncidentService::class)->record(
                emailType: 'Violation Confirmation Notification',
                errorMessage: $e->getMessage(),
                relatedRecordType: 'AdminNotification',
                relatedRecordId: $notification->admin_notification_id,
            );
        }
    }
}

private function sendSanctionRequestEmail(AdminNotification $notification): void
{
    $admins = Admin::query()
        ->with('adminInfo')
        ->get()
        ->filter(function (Admin $admin): bool {
            // Only operational Admins receive student sanction requests.
            // Super Admin and OSS Staff are excluded.
            return ! $admin->isItAdministrator()
                && ! $admin->isOssStaff();
        });

    // If the notification is addressed to one specific admin,
    // only email that administrator.
    if ($notification->recipient_id !== null) {
        $admins = $admins->filter(
            fn (Admin $admin): bool =>
                (int) $admin->id === (int) $notification->recipient_id
        );
    }

    foreach ($admins as $admin) {
        $email = trim((string) $admin->email);

        if ($email === '') {
            continue;
        }

        try {
            Mail::to($email)->send(
                new SystemNotification(
                    notificationTitle: (string) (
                        $notification->title ?? 'Disciplinary Sanction Request'
                    ),
                    notificationMessage: (string) $notification->message,
                    recipientName: (string) ($admin->username ?: 'Administrator'),
                    actionUrl: $this->openUrl($notification),
                    actionText: 'View Sanction Request',
                )
            );
        } catch (\Throwable $e) {
            app(EmailIncidentService::class)->record(
                emailType: 'Sanction Request Notification',
                errorMessage: $e->getMessage(),
                relatedRecordType: 'AdminNotification',
                relatedRecordId: $notification->admin_notification_id,
            );
        }
    }
}
private function sendOssStaffDecisionEmail(AdminNotification $notification): void
{
    $recipientId = (int) ($notification->recipient_id ?? 0);

    if ($recipientId <= 0) {
        return;
    }

    $ossStaff = Admin::query()
        ->with('adminInfo')
        ->find($recipientId);

    if (! $ossStaff) {
        Log::warning('OSS Staff notification recipient not found', [
            'notification_id' => $notification->admin_notification_id,
            'recipient_id' => $recipientId,
        ]);

        return;
    }

    // Make sure the recipient is actually an OSS Staff account.
    if (! $ossStaff->isOssStaff()) {
        Log::warning('Notification recipient is not an OSS Staff account', [
            'notification_id' => $notification->admin_notification_id,
            'recipient_id' => $recipientId,
        ]);

        return;
    }

    $email = trim((string) $ossStaff->email);

    if ($email === '') {
        Log::warning('OSS Staff notification recipient has no email', [
            'notification_id' => $notification->admin_notification_id,
            'recipient_id' => $recipientId,
        ]);

        return;
    }

    try {
        Mail::to($email)->send(
            new SystemNotification(
                notificationTitle: (string) (
                    $notification->title ?? 'Violation Update'
                ),
                notificationMessage: (string) $notification->message,
                recipientName: (string) ($ossStaff->username ?: 'OSS Staff'),
                actionUrl: $this->openUrl($notification),
                actionText: 'View Violation',
            )
        );
    } catch (\Throwable $e) {
        app(EmailIncidentService::class)->record(
            emailType: 'OSS Staff Violation Decision Notification',
            errorMessage: $e->getMessage(),
            relatedRecordType: 'AdminNotification',
            relatedRecordId: $notification->admin_notification_id,
        );
    }
}
}