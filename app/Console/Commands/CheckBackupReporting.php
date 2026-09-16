<?php

namespace App\Console\Commands;

use App\Models\AdminNotification;
use App\Models\BackupHistory;
use App\Services\AdminNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * "Backup Has Not Reported" detection (§3).
 *
 * A silently-absent backup is the most dangerous failure mode: nothing writes a
 * `failed` row, so §7's failure alert never fires and the system looks healthy
 * while there is no recovery point at all. This command catches that case by
 * comparing the newest report against the expected cadence.
 *
 * Read-only with respect to backup data — it only reads `backup_history` and
 * writes a Super Admin system alert. It never creates or edits a backup record.
 *
 * Schedule it shortly after the expected backup window. Example (in the same
 * style as the existing backup schedule in bootstrap/app.php):
 *
 *     $schedule->command('backup:check-reporting')->dailyAt('04:30')
 *         ->timezone('Asia/Manila');
 */
class CheckBackupReporting extends Command
{
    protected $signature = 'backup:check-reporting
                            {--hours=26 : Hours without any backup report before alerting}
                            {--dry-run : Report what would happen without writing a notification}';

    protected $description = 'Alert the Super Admin when no backup has reported within the expected window.';

    /** Notification type reused for de-duplication. */
    private const TYPE = 'system_backup_not_reporting';

    public function handle(): int
    {
        if (! Schema::hasTable('backup_history')) {
            $this->warn('backup_history table is absent; nothing to check.');
            return self::SUCCESS;
        }

        $thresholdHours = max(1, (int) $this->option('hours'));
        $dryRun = (bool) $this->option('dry-run');

        $latest = BackupHistory::query()
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->first();

        $now = now();

        if (! $latest) {
            return $this->raise(
                now: $now,
                title: 'No Backup Has Ever Reported',
                message: 'PUPTracker has never received a backup report. Verify that the backup service is configured and running — there is currently no recovery point.',
                backupName: null,
                dryRun: $dryRun,
            );
        }

        // `completed_at` is the report time for externally produced backups; fall
        // back to created_at so a row with a null timestamp still counts.
        $lastReportedAt = $latest->completed_at ?? $latest->created_at;

        if (! $lastReportedAt) {
            return $this->raise(
                now: $now,
                title: 'Backup Reporting State Unknown',
                message: 'The most recent backup record has no completion timestamp, so backup health cannot be determined.',
                backupName: $latest->backup_name,
                dryRun: $dryRun,
            );
        }

        $hoursSince = $lastReportedAt->diffInHours($now);

        if ($hoursSince < $thresholdHours) {
            $this->info(sprintf(
                'Backup reporting is healthy: last report %s (%dh ago, threshold %dh).',
                $lastReportedAt->format('Y-m-d H:i:s'),
                $hoursSince,
                $thresholdHours
            ));

            return self::SUCCESS;
        }

        return $this->raise(
            now: $now,
            title: 'Backup Has Not Reported',
            message: sprintf(
                'No backup has reported in %d hours (last report: %s). The scheduled backup may not be running. Verify the backup service — recovery point coverage is unconfirmed.',
                $hoursSince,
                $lastReportedAt->format('Y-m-d H:i:s')
            ),
            backupName: $latest->backup_name,
            dryRun: $dryRun,
        );
    }

    private function raise(
        \Illuminate\Support\Carbon $now,
        string $title,
        string $message,
        ?string $backupName,
        bool $dryRun,
    ): int {
        if ($dryRun) {
            $this->warn('[dry-run] Would alert the Super Admin: ' . $title);
            return self::SUCCESS;
        }

        // De-duplicate: one open alert at a time. Re-alerting every day for the
        // same unresolved outage would flood the Super Admin inbox (§5).
        $alreadyOpen = AdminNotification::query()
            ->where('notification_type', self::TYPE)
            ->where('is_read', false)
            ->exists();

        if ($alreadyOpen) {
            $this->info('An unread "backup has not reported" alert already exists; not duplicating.');
            return self::SUCCESS;
        }

        try {
            // Target id 0 = "no specific backup record"; openUrl() falls back to
            // the backup history list in that case.
            app(AdminNotificationService::class)->createSystemAlert(
                type: self::TYPE,
                title: $title,
                message: $message,
                targetType: 'backup',
                targetId: 0,
                priority: AdminNotification::PRIORITY_CRITICAL,
            );
        } catch (\Throwable $e) {
            $this->error('Unable to write the Super Admin alert: ' . $e->getMessage());
            Log::error('Backup reporting check failed to notify', ['error' => $e->getMessage()]);

            return self::FAILURE;
        }

        $this->warn('Super Admin alerted: ' . $title . ($backupName ? ' (last: ' . $backupName . ')' : ''));

        return self::SUCCESS;
    }
}
