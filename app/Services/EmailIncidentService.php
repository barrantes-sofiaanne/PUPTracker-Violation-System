<?php

namespace App\Services;

use App\Models\AdminNotification;
use App\Models\EmailIncident;
use Illuminate\Support\Facades\Log;

/**
 * Risk No. 7 — Incident Reporting & Response.
 *
 * Central service for recording definitive email dispatch failures:
 *   1. Writes a safe entry to the Laravel error log.
 *   2. Records a deduplicated email_incidents row (one per operation).
 *   3. Creates a Super Admin in-app notification linking to the incident.
 *
 * Never logs or stores credentials, SMTP passwords, API keys, or tokens.
 */
class EmailIncidentService
{
    /**
     * Dedupe window (seconds) for repeated failures of the same operation.
     * Prevents one notification per retry attempt.
     */
    public const DEDUPE_WINDOW_SECONDS = 300;

    /**
     * Record an email dispatch failure.
     *
     * @param  string      $emailType          e.g. 'OTP', 'Password Reset', 'Test Email'
     * @param  string      $errorMessage       raw exception message (sanitized before storing)
     * @param  string|null $relatedRecordType  e.g. 'Student', 'Reservation'
     * @param  string|int|null $relatedRecordId
     */
    public function record(
        string $emailType,
        string $errorMessage,
        ?string $relatedRecordType = null,
        string|int|null $relatedRecordId = null
    ): ?EmailIncident {
        try {
            $safeMessage = $this->sanitize($errorMessage);
            $occurredAt = now();

            // Log first — this is the "real-time system error logging" layer.
            Log::error('Email Dispatch Failed', [
                'email_type' => $emailType,
                'related_record_type' => $relatedRecordType,
                'related_record_id' => $relatedRecordId,
                'error' => $safeMessage,
                'timestamp' => $occurredAt->toDateTimeString(),
            ]);

            // Dedupe: reuse an open (non-resolved) incident for the same operation.
            $existing = EmailIncident::query()
                ->where('email_type', $emailType)
                ->where(function ($query) use ($relatedRecordType, $relatedRecordId): void {
                    $query->whereNull('related_record_type')
                        ->orWhere('related_record_type', $relatedRecordType);
                })
                ->where(function ($query) use ($relatedRecordType, $relatedRecordId): void {
                    $query->whereNull('related_record_id')
                        ->orWhere('related_record_id', (string) $relatedRecordId);
                })
                ->whereIn('status', [EmailIncident::STATUS_FAILED, EmailIncident::STATUS_ACKNOWLEDGED])
                ->where('occurred_at', '>=', now()->subSeconds(self::DEDUPE_WINDOW_SECONDS))
                ->latest('occurred_at')
                ->first();

            if ($existing) {
                $existing->update([
                    'safe_error_message' => $safeMessage,
                    'occurred_at' => $occurredAt,
                ]);

                return $existing;
            }

            $incident = EmailIncident::create([
                'incident_type' => 'email_dispatch_failed',
                'email_type' => $emailType,
                'related_record_type' => $relatedRecordType,
                'related_record_id' => $relatedRecordId !== null ? (string) $relatedRecordId : null,
                'safe_error_message' => $safeMessage,
                'status' => EmailIncident::STATUS_FAILED,
                'occurred_at' => $occurredAt,
            ]);

            $this->notifySuperAdmin($incident);

            return $incident;
        } catch (\Throwable $exception) {
            // Incident bookkeeping must never take down the primary operation.
            Log::warning('Failed to record email incident', [
                'email_type' => $emailType,
                'error' => $this->sanitize($exception->getMessage()),
            ]);

            return null;
        }
    }

    /**
     * Create the in-app Super Admin notification.
     */
    protected function notifySuperAdmin(EmailIncident $incident): void
    {
        $link = $this->incidentUrl($incident);

        AdminNotification::create([
            'message' => 'Email Dispatch Failed — ' . ($incident->email_type ?: 'System Email'),
            'link' => $link,
            'notification_type' => 'system_email_failure',
            'is_read' => false,
            'created_at' => now(),
        ]);
    }

    protected function incidentUrl(EmailIncident $incident): ?string
    {
        try {
            if (! app()->routesAreCached() && \Illuminate\Support\Facades\Route::has('admin.super-admin.email-incidents.show')) {
                return route('admin.super-admin.email-incidents.show', $incident->incident_id);
            }

            return null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Strip anything that looks like a credential before storing/logging.
     */
    public function sanitize(string $message): string
    {
        // Truncate to a safe length.
        $message = mb_substr($message, 0, 500);

        // Scrub common secret patterns (values only — never reveal what was removed).
        $patterns = [
            '/password\s*[:=]\s*\S+/i',
            '/passwd\s*[:=]\s*\S+/i',
            '/secret\s*[:=]\s*\S+/i',
            '/api[_-]?key\s*[:=]\s*\S+/i',
            '/token\s*[:=]\s*\S+/i',
            '/client[_-]?secret\s*[:=]\s*\S+/i',
            '/authorization\s*[:=]\s*\S+/i',
            '/app[_-]?key\s*[:=]\s*\S+/i',
            '/MAIL[_-]?PASSWORD\s*[:=]\s*\S+/i',
            '/DB[_-]?PASSWORD\s*[:=]\s*\S+/i',
        ];

        foreach ($patterns as $pattern) {
            $message = preg_replace($pattern, '[redacted]', $message) ?? $message;
        }

        return trim($message) !== '' ? trim($message) : 'Email dispatch failed.';
    }
}
