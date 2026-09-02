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
     */
    public function create(
        string $type,
        string $title,
        string $message,
        string $targetType,
        int $targetId,
        ?string $link = null
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
        if (! Route::has('security.violations.show')) {
            return null;
        }

        $violation = Violation::query()->find($targetId);

        if (! $violation) {
            return null;
        }

        return route('security.violations.show', ['student_number' => $violation->student_number]);
    }
}
