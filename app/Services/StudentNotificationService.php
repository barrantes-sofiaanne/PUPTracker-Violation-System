<?php

namespace App\Services;

use App\Mail\StudentNotification;
use App\Models\Announcement;
use App\Models\Notification;
use App\Models\SanctionRequest;
use App\Models\User;
use App\Models\Violation;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * Target-aware notifications for students.
 *
 * Mirrors the AdminNotificationService contract: an event creates a
 * notification that points at an exact target record (target_type/target_id);
 * the backend verifies authorization and resolves the correct student module
 * route. This prevents a notification from routing a student to the wrong page.
 */
class StudentNotificationService
{
    /**
     * Create a student notification after the underlying action succeeded.
     *
     * The notification is stamped with the OWNING ACCOUNT id (`recipient_id`),
     * resolved from users_tbl by student number. Authorisation later keys off
     * that account id, so a student number typo can never deliver one student's
     * private notification to another account.
     *
     * @param  int|null  $userId  Owner account id. Omit to let the service
     *                            resolve it from $studentNumber.
     */
    public function create(
        string $studentNumber,
        string $type,
        string $title,
        string $message,
        string $targetType,
        int $targetId,
        ?int $userId = null
    ): Notification {
        $data = [
            'student_number' => $studentNumber,
            'message' => $message,
            'is_read' => false,
            'notification_type' => $type,
            'recipient_type' => 'student',
            'created_at' => now(),
        ];

        // Account ownership. Always stamped when resolvable so the row is
        // protected by the account key from the moment it is written.
        $ownerId = $userId ?? $this->resolveOwnerId($studentNumber);

        if ($ownerId !== null && Schema::hasColumn('notifications_tbl', 'recipient_id')) {
            $data['recipient_id'] = $ownerId;
        }

        if (Schema::hasColumn('notifications_tbl', 'title')) {
            $data['title'] = $title;
        }
        if (Schema::hasColumn('notifications_tbl', 'target_type')) {
            $data['target_type'] = $targetType;
        }
        if (Schema::hasColumn('notifications_tbl', 'target_id')) {
            $data['target_id'] = $targetId;
        }

        $notification = Notification::create($data);

// Send email only for decision-related violation notifications.
// The existing in-app notification remains unchanged.
if (
    $type === 'violation'
    && in_array($title, ['Violation Confirmed', 'Violation Rejected'], true)
) {
    try {
        $this->sendDecisionEmail(
            notification: $notification,
            studentNumber: $studentNumber,
        );
    } catch (\Throwable $e) {
        Log::warning('Failed to send student notification email', [
            'notification_id' => $notification->notification_id ?? null,
            'student_number' => $studentNumber,
            'error' => $e->getMessage(),
        ]);
    }
}

return $notification;
    }

    /**
     * Resolve the owning account id for a student number.
     *
     * Returns null when the account cannot be resolved (e.g. the number belongs
     * to no registered account); the notification is then written without an
     * ownership key and is only reachable through the bounded legacy fallback.
     */
    protected function resolveOwnerId(string $studentNumber): ?int
    {
        if (trim($studentNumber) === '') {
            return null;
        }

        $userId = User::query()
            ->where('student_number', $studentNumber)
            ->orderBy('user_id')
            ->value('user_id');

        return $userId !== null ? (int) $userId : null;
    }

    /**
     * Resolve the exact student-module URL for a notification target.
     * Returns null when the target record no longer exists or the route is
     * unavailable (the caller reports the record unavailable).
     */
    public function openUrl(Notification $notification): ?string
    {
        return match ($notification->target_type) {
            'violation' => $this->violationUrl((int) $notification->target_id, $notification->student_number),
            'sanction_request' => $this->sanctionRequestUrl((int) $notification->target_id, $notification->student_number),
            'announcement' => $this->announcementUrl((int) $notification->target_id),
            default => $notification->link ?: null,
        };
    }

    private function violationUrl(int $targetId, string $studentNumber): ?string
    {
        if (! Route::has('student.record')) {
            return null;
        }

        $violation = Violation::query()->find($targetId);

        if (! $violation) {
            return null;
        }

        // Always land the student on their own record.
        return route('student.record', ['student_number' => $studentNumber]) . '#violation-tab';
    }

    private function sanctionRequestUrl(int $targetId, string $studentNumber): ?string
    {
        if (! Route::has('student.record')) {
            return null;
        }

        $request = SanctionRequest::query()->find($targetId);

        if (! $request) {
            return null;
        }

        return route('student.record', ['student_number' => $studentNumber]) . '#sanction-tab';
    }

    private function announcementUrl(int $targetId): ?string
    {
        if (! Route::has('student.announcements')) {
            return null;
        }

        $announcement = Announcement::query()->find($targetId);

        if (! $announcement) {
            return null;
        }

        return route('student.announcements');
    }

    /**
 * Send an email for a student violation decision notification.
 */
private function sendDecisionEmail(
    Notification $notification,
    string $studentNumber
): void {
    $student = User::query()
        ->where('student_number', $studentNumber)
        ->first();

    if (! $student) {
        return;
    }

    $email = trim((string) $student->email);

    if ($email === '') {
        return;
    }

    $studentName = trim(
        ($student->first_name ?? '') . ' ' .
        ($student->last_name ?? '')
    );

    if ($studentName === '') {
        $studentName = 'Student';
    }

    $actionUrl = $this->openUrl($notification);

    try {
        Mail::to($email)->send(
            new StudentNotification(
                notificationTitle: (string) (
                    $notification->title ?? 'PUPTracker Notification'
                ),
                notificationMessage: (string) $notification->message,
                recipientName: $studentName,
                actionUrl: $actionUrl,
                actionText: 'View Student Record',
            )
        );
    } catch (\Throwable $e) {
        // Email failure must never prevent the notification
        // or the underlying Admin decision from succeeding.

        app(\App\Services\EmailIncidentService::class)->record(
            emailType: 'Student Violation Decision Notification',
            errorMessage: $e->getMessage(),
            relatedRecordType: 'Notification',
            relatedRecordId: $notification->notification_id ?? null,
        );
    }
}
}
