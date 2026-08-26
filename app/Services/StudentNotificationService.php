<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\Notification;
use App\Models\SanctionRequest;
use App\Models\Violation;
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
     */
    public function create(
        string $studentNumber,
        string $type,
        string $title,
        string $message,
        string $targetType,
        int $targetId
    ): Notification {
        $data = [
            'student_number' => $studentNumber,
            'message' => $message,
            'is_read' => false,
            'notification_type' => $type,
            'recipient_type' => 'student',
            'created_at' => now(),
        ];

        if (Schema::hasColumn('notifications_tbl', 'title')) {
            $data['title'] = $title;
        }
        if (Schema::hasColumn('notifications_tbl', 'target_type')) {
            $data['target_type'] = $targetType;
        }
        if (Schema::hasColumn('notifications_tbl', 'target_id')) {
            $data['target_id'] = $targetId;
        }

        return Notification::create($data);
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
}
