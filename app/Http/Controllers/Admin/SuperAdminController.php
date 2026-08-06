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

        return view('admin.super-admin.dashboard', compact(
            'totalLogs',
            'todayLogs',
            'maintenanceStatus',
            'latestLog'
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
