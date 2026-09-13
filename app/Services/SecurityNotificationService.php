<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Violation;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * Target-aware notifications for security officers.
 *
 * Mirrors StudentNotificationService / AdminNotificationService: an event
 * creates a notification that points at an exact target record
 * (target_type/target_id). The notification carries recipient_type =
 * 'security' and no student_number, and can be deep-linked to the relevant
 * security page (e.g. a specific student's violation history).
 */
class SecurityNotificationService
{
    /**
     * Create a security notification after the underlying action succeeded.
     *
     * @param  int|null  $recipientId  The security account id the notification is
     *                                 addressed to. When null the row is a
     *                                 broadcast to all security officers (used
     *                                 only for pre-policy / backward-compat rows;
     *                                 new code should always pass the exact id).
     */
    public function create(
        string $type,
        string $title,
        string $message,
        string $targetType,
        int $targetId,
        ?string $link = null,
        ?int $recipientId = null
    ): Notification {
        $data = [
            'message' => $message,
            'is_read' => false,
            'notification_type' => $type,
            'recipient_type' => 'security',
            'created_at' => now(),
        ];

        // student_number is nullable for security rows; if the schema does not
        // allow null on this legacy table we fall back to an empty string.
        $data['student_number'] = '';

        if (Schema::hasColumn('notifications_tbl', 'title')) {
            $data['title'] = $title;
        }
        if (Schema::hasColumn('notifications_tbl', 'target_type')) {
            $data['target_type'] = $targetType;
        }
        if (Schema::hasColumn('notifications_tbl', 'target_id')) {
            $data['target_id'] = $targetId;
        }
        if (Schema::hasColumn('notifications_tbl', 'recipient_id')) {
            $data['recipient_id'] = $recipientId;
        }
        if ($link !== null && Schema::hasColumn('notifications_tbl', 'link')) {
            $data['link'] = $link;
        }

        return Notification::create($data);
    }

    /**
     * Resolve the exact security-module URL for a notification target.
     * Returns null when the target is unavailable (caller falls back).
     */
    public function openUrl(Notification $notification): ?string
    {
        return match ($notification->target_type) {
            'violation' => $this->violationUrl((int) $notification->target_id),
            default => $notification->link ?: null,
        };
    }

    private function violationUrl(int $targetId): ?string
    {
        $violation = Violation::query()->find($targetId);

        if (! $violation) {
            return null;
        }

        // A CONFIRMED violation reflects on the student's record page, so the
        // notification deep-links there and highlights the exact violation.
        if (strtolower((string) $violation->status) === Violation::STATUS_CONFIRMED) {
            if (! Route::has('security.violations.show')) {
                return null;
            }

            return route('security.violations.show', [
                'student_number' => $violation->student_number,
                'highlight_violation' => $violation->violation_id,
            ]);
        }

        // PENDING (awaiting admin confirmation) and DENIED violations do NOT
        // reflect on the student record page; the officer tracks them under the
        // Submitted Violations tab of the combined Violations page, so the
        // notification lands on that page with the tab open and highlights the
        // row.
        if (! Route::has('security.violations.submitted')) {
            return null;
        }

        return route('security.violations.submitted', [
            'highlight_violation' => $violation->violation_id,
        ]);
    }
}
