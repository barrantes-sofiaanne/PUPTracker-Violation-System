<?php

namespace App\Services;

use App\Models\AdminNotification;
use App\Models\EmailIncident;
use App\Models\SanctionRequest;
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
            'sanction_request' => SanctionRequest::find($notification->target_id)
                && Route::has('admin.sanctions.requests.show')
                ? route('admin.sanctions.requests.show', $notification->target_id)
                : null,
            'email_incident' => EmailIncident::find($notification->target_id)
                && Route::has('admin.super-admin.email-incidents.show')
                ? route('admin.super-admin.email-incidents.show', $notification->target_id)
                : null,
            default => null,
        };
    }
}