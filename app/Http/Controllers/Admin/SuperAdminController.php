<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class SuperAdminController extends Controller
{
    public function dashboard()
    {
        $totalLogs = AuditLog::count();
        $todayLogs = AuditLog::whereDate('created_at', today())->count();
        $maintenanceStatus = app()->isDownForMaintenance();
        $latestLog = AuditLog::latest('created_at')->first();

        // MFA coverage: total admins vs admins with TOTP enabled.
        $totalAdmins = Admin::count();
        $mfaEnabledAdmins = Admin::where('mfa_totp_enabled', true)->count();

        // Maintenance history: last + next review (quarterly ~3 months).
        $latestMaintenance = \App\Models\MaintenanceRecord::query()
            ->latest('maintenance_date')
            ->first();
        $maintenanceNextReview = $latestMaintenance
            ? \Illuminate\Support\Carbon::parse($latestMaintenance->maintenance_date)->addMonths(3)
            : null;
        $maintenanceUpToDate = $maintenanceNextReview
            ? $maintenanceNextReview->isFuture()
            : false;

        // Backup status: latest recorded outcome from backup_history.
        // Guarded so the dashboard never breaks when the table/columns are
        // absent (pre-policy deployments that have not run the migration yet).
        $backupTableExists = \Illuminate\Support\Facades\Schema::hasTable('backup_history');

        $latestBackup = $backupTableExists
            ? \App\Models\BackupHistory::query()->latest('completed_at')->first()
            : null;

        $lastSuccessfulBackup = $backupTableExists
            ? \App\Models\BackupHistory::query()->successful()->latest('completed_at')->first()
            : null;

        // Only query "full" when the type column exists (avoids a SQL error on
        // schemas that predate the backup-policy migration).
        $lastSuccessfulFull = ($backupTableExists && \App\Models\BackupHistory::hasColumn('backup_type'))
            ? \App\Models\BackupHistory::query()->successful()->full()->latest('completed_at')->first()
            : $lastSuccessfulBackup;

        $lastFailedBackup = $backupTableExists
            ? \App\Models\BackupHistory::query()->failed()->latest('completed_at')->first()
            : null;

        $backupIntervalDays = max(1, (int) config('backup.full_interval_days', 14));

        // "Healthy" requires a recent successful backup AND no recent failure.
        $recentSuccess = $lastSuccessfulBackup
            && \Illuminate\Support\Carbon::parse($lastSuccessfulBackup->completed_at)->isAfter(now()->subDays(2));
        $recentFailure = $lastFailedBackup
            && \Illuminate\Support\Carbon::parse($lastFailedBackup->completed_at)->isAfter(now()->subDays(2));
        $backupHealthy = $recentSuccess && ! $recentFailure;

        // Next FULL due is computed from the last SUCCESSFUL FULL (elapsed time,
        // never calendar dates).
        $nextFullDue = $lastSuccessfulFull
            ? \Illuminate\Support\Carbon::parse($lastSuccessfulFull->completed_at)->addDays($backupIntervalDays)
            : null;
        $fullOverdue = $lastSuccessfulFull === null
            || $nextFullDue->isPast();

        return view('admin.super-admin.dashboard', compact(
            'totalLogs',
            'todayLogs',
            'maintenanceStatus',
            'latestLog',
            'totalAdmins',
            'mfaEnabledAdmins',
            'latestMaintenance',
            'maintenanceNextReview',
            'maintenanceUpToDate',
            'latestBackup',
            'backupHealthy',
            'lastSuccessfulBackup',
            'lastSuccessfulFull',
            'lastFailedBackup',
            'backupIntervalDays',
            'nextFullDue',
            'fullOverdue'
        ));
    }

    public function auditTrail(Request $request)
    {
        $query = AuditLog::query()->latest('created_at');

        if ($request->filled('actor_type')) {
            $query->where('actor_type', $request->string('actor_type')->toString());
        }

        if ($request->filled('module')) {
            $query->where('module', $request->string('module')->toString());
        }

        if ($request->filled('keyword')) {
            $keyword = '%' . $request->string('keyword')->toString() . '%';
            $query->where(function ($innerQuery) use ($keyword): void {
                $innerQuery->where('action', 'like', $keyword)
                    ->orWhere('description', 'like', $keyword);
            });
        }

        $logs = $query->paginate(20)->withQueryString();

        $actorTypes = AuditLog::query()
            ->select('actor_type')
            ->whereNotNull('actor_type')
            ->distinct()
            ->orderBy('actor_type')
            ->pluck('actor_type');

        $modules = AuditLog::query()
            ->select('module')
            ->whereNotNull('module')
            ->distinct()
            ->orderBy('module')
            ->pluck('module');

        return view('admin.super-admin.audit-trail', compact('logs', 'actorTypes', 'modules'));
    }

    /**
     * Global Evidence Security Events viewer (Super Admin).
     *
     * These rows are the evidence-specific security log (WHAT happened to the
     * evidence). This is a read-only list with filters; event rows are only ever
     * written by the evidence capture/access flow.
     */
    public function evidenceSecurityEvents(Request $request)
    {
        $query = \App\Models\EvidenceSecurityEvent::query()
            ->with(['violation', 'evidence'])
            ->latest('created_at');

        if ($request->filled('event_type')) {
            $query->where('event_type', $request->string('event_type')->toString());
        }

        if ($request->filled('severity')) {
            $query->where('severity', $request->string('severity')->toString());
        }

        if ($request->filled('actor_type')) {
            $query->where('actor_type', $request->string('actor_type')->toString());
        }

        if ($request->filled('evidence_id')) {
            $query->where('evidence_id', (int) $request->string('evidence_id')->toString());
        }

        if ($request->filled('keyword')) {
            $keyword = '%' . $request->string('keyword')->toString() . '%';
            $query->where(function ($q) use ($keyword): void {
                $q->where('event_type', 'like', $keyword)
                    ->orWhere('ip_address', 'like', $keyword);
            });
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->string('from')->toString());
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->string('to')->toString());
        }

        $events = $query->paginate(25)->withQueryString();

        $eventTypes = \App\Models\EvidenceSecurityEvent::query()
            ->select('event_type')->distinct()->orderBy('event_type')->pluck('event_type');

        $severities = \App\Models\EvidenceSecurityEvent::query()
            ->select('severity')->distinct()->orderBy('severity')->pluck('severity');

        return view('admin.super-admin.evidence-security-events', compact('events', 'eventTypes', 'severities'));
    }

    /**
     * System Logs viewer (Super Admin only).
     *
     * Reads the Laravel application log file for technical diagnostics. This is
     * intentionally NOT exposed to Normal Admins. No secrets are ever rendered:
     * the view shows the tail of the log with the latest entries only.
     */
    public function systemLogs(Request $request)
    {
        $channel = (string) config('logging.default', 'single');
        $path = $channel === 'daily'
            ? storage_path('logs/laravel-' . now()->format('Y-m-d') . '.log')
            : storage_path('logs/laravel.log');

        // Try stderr/stack channel fallbacks to the conventional single file.
        if (! is_file($path)) {
            $path = storage_path('logs/laravel.log');
        }

        $raw = is_file($path) ? (string) file_get_contents($path) : '';

        // Only show the most recent N bytes / lines (operational tail).
        $maxBytes = 256 * 1024; // 256 KB
        if (strlen($raw) > $maxBytes) {
            $raw = substr($raw, -$maxBytes);
        }

        $lines = collect(explode("\n", $raw))
            ->filter(fn ($line) => trim((string) $line) !== '')
            ->reverse()
            ->take(300)
            ->reverse()
            ->values();

        $level = $request->string('level', '')->toString();
        if ($level !== '') {
            $needle = strtoupper($level) . ':';
            $lines = $lines->filter(fn ($line) => str_contains((string) $line, $needle))->values();
        }

        $levels = ['DEBUG', 'INFO', 'NOTICE', 'WARNING', 'ERROR', 'CRITICAL'];

        return view('admin.super-admin.system-logs', compact('lines', 'level', 'levels', 'path'));
    }

    public function maintenanceConfiguration()
    {
        $maintenanceStatus = app()->isDownForMaintenance();

        $latestMaintenanceLog = AuditLog::query()
            ->where('module', 'Maintenance Configuration')
            ->latest('created_at')
            ->first();

        return view('admin.super-admin.maintenance', compact('maintenanceStatus', 'latestMaintenanceLog'));
    }

    public function auditControlPlan()
    {
        $correctivePlan = [
            [
                'finding_no' => 1,
                'corrective_action' => 'Add HTTP Strict Transport Security, CSP, X-Frame-Options, and X-Content-Type-Options headers via server/.htaccess configuration.',
                'owner' => 'Lead Developer',
                'resources' => 'OWASP Secure Headers Project, Mozilla Web Security Guidelines',
                'target_date' => 'Within 1 month',
                'status' => 'Completed',
            ],
            [
                'finding_no' => 2,
                'corrective_action' => 'Enable MFA (email OTP or authenticator app) for Super Admin and Security Personnel logins.',
                'owner' => 'System Administrator / Lead Developer',
                'resources' => 'MFA library/integration and existing email service (PHPMailer)',
                'target_date' => 'Within 1 month',
                'status' => 'Completed',
            ],
            [
                'finding_no' => 3,
                'corrective_action' => 'Publish a security contact email/reporting form on the homepage/footer.',
                'owner' => 'Lead Developer',
                'resources' => 'ISO/IEC 27001 Annex A.5.24 (Incident Management Planning)',
                'target_date' => 'Within 1 month',
                'status' => 'Completed',
            ],
            [
                'finding_no' => 4,
                'corrective_action' => 'Set up a documented patch/update schedule and track applied updates.',
                'owner' => 'Lead Developer',
                'resources' => 'ISO/IEC 27001 Annex A.12.8, OWASP Secure Coding Guidelines',
                'target_date' => 'Within 3 months',
                'status' => 'Completed',
            ],
            [
                'finding_no' => 5,
                'corrective_action' => 'Set up and document a verifiable weekly backup schedule with monthly restoration tests.',
                'owner' => 'Database Administrator',
                'resources' => 'Hosting/database access + MySQL Workbench, ISO/IEC 27001 Annex A.12.5',
                'target_date' => 'Within 3 months',
                'status' => 'Completed',
            ],
        ];

        $treatmentPlan = [
            [
                'no' => 1,
                'risk_reference' => 'Risk No. 2: Password & Authentication Controls',
                'treatment' => 'Implement password_hash() for all credentials, enforce strong password complexity rules, and add login rate limiting.',
                'owner' => 'Developer',
                'resources' => 'PHP security libraries and developer support',
                'target_date' => 'Within this month',
                'status' => 'Completed',
            ],
            [
                'no' => 2,
                'risk_reference' => 'Risk No. 3: Data Backup & Recovery Procedures',
                'treatment' => 'Set up automated daily MySQL backups with off-site cloud storage and conduct monthly backup restoration drills.',
                'owner' => 'Database Administrator',
                'resources' => 'MySQL Workbench, backup storage, firewall/cloudflare access',
                'target_date' => 'Within this month',
                'status' => 'Completed',
            ],
            [
                'no' => 3,
                'risk_reference' => 'Risk No. 4: Network & System Security',
                'treatment' => 'Install SSL/TLS certificates and implement secure transport and local buffering controls for temporary offline handling.',
                'owner' => 'Network Developer / Web Developer',
                'resources' => 'SSL certificate and network support resources',
                'target_date' => 'Within this month',
                'status' => 'Completed',
            ],
            [
                'no' => 4,
                'risk_reference' => 'Risk No. 5: Data Privacy & Protection Practices',
                'treatment' => 'Restrict student accounts to self-record views only and protect sensitive personal data fields (RA 10173).',
                'owner' => 'System Administrator / OSS Head / Students',
                'resources' => 'Data privacy compliance policy and encryption controls',
                'target_date' => 'Within this month',
                'status' => 'Completed',
            ],
            [
                'no' => 5,
                'risk_reference' => 'Risk No. 6: Software Licensing & Update Management',
                'treatment' => 'Schedule quarterly maintenance for dependency updates, framework security patches, and server OS maintenance.',
                'owner' => 'IT Support / System Administrator',
                'resources' => 'Patch update logs and post-update system stability reports',
                'target_date' => 'Within this month',
                'status' => 'Completed',
            ],
            [
                'no' => 6,
                'risk_reference' => 'Risk No. 7: Incident Reporting & Response Procedures',
                'treatment' => 'Implement real-time system error logging and an in-app notification backup whenever email dispatch fails.',
                'owner' => 'Lead Developer / OSS Staff',
                'resources' => 'Error logging framework and SMTP mail queue',
                'target_date' => 'Within this month',
                'status' => 'Completed',
            ],
        ];

        return view('admin.super-admin.audit-control-plan', compact('correctivePlan', 'treatmentPlan'));
    }

    public function updateMaintenanceConfiguration(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'in:enable,disable'],
        ]);

        $admin = Auth::guard('admin')->user();
        $adminId = $admin instanceof Admin ? $admin->id : null;
        $adminEmail = $admin instanceof Admin ? $admin->email : 'unknown';
        $isDown = app()->isDownForMaintenance();
        $action = $validated['action'];

        if ($action === 'enable') {
            if ($isDown) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => 'Maintenance mode is already enabled.',
                    ], 422);
                }

                return back()->with('info', 'Maintenance mode is already enabled.');
            }

            $maintenanceSecret = Str::random(40);
            $exitCode = Artisan::call('down', [
                '--secret' => $maintenanceSecret,
            ]);

            if ($exitCode !== 0) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => 'Unable to enable maintenance mode. Please try again.',
                    ], 500);
                }

                return back()->with('error', 'Unable to enable maintenance mode. Please try again.');
            }

            AuditLog::create([
                'actor_type' => 'admin',
                'actor_id' => $adminId,
                'action' => 'Enabled Maintenance Mode',
                'module' => 'Maintenance Configuration',
                'description' => 'Maintenance mode enabled by IT Administrator ' . $adminEmail . '.',
                'created_at' => now(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Maintenance mode has been enabled. Save the bypass key before continuing.',
                    'secret' => $maintenanceSecret,
                    'bypass_url' => url($maintenanceSecret),
                ]);
            }

            return redirect()
                ->to('/' . $maintenanceSecret)
                ->with('success', 'Maintenance mode has been enabled. Your maintenance bypass URL is active for this browser.');
        }

        if (!$isDown) {
            return back()->with('info', 'Maintenance mode is already disabled.');
        }

        Artisan::call('up');

        AuditLog::create([
            'actor_type' => 'admin',
            'actor_id' => $adminId,
            'action' => 'Disabled Maintenance Mode',
            'module' => 'Maintenance Configuration',
            'description' => 'Maintenance mode disabled by IT Administrator ' . $adminEmail . '.',
            'created_at' => now(),
        ]);

        return back()->with('success', 'Maintenance mode has been disabled.');
    }
}
