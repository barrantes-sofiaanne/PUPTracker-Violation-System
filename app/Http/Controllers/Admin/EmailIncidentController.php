<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\EmailIncident;
use App\Services\EmailIncidentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Risk No. 7 — Super Admin only email incident (system log) management.
 *
 * Routes live inside the `super.admin` middleware group, so ordinary
 * Admin users cannot view or act on system-wide incidents.
 */
class EmailIncidentController extends Controller
{
    /**
     * System Logs — list of recorded email incidents.
     */
    public function index(Request $request): View
    {
        $query = EmailIncident::query()
            ->with(['acknowledgedBy', 'resolvedBy'])
            ->latest('occurred_at');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('email_type')) {
            $query->where('email_type', $request->string('email_type')->toString());
        }

        $incidents = $query->paginate(15)->withQueryString();

        $openCount = EmailIncident::whereIn('status', [
            EmailIncident::STATUS_FAILED,
            EmailIncident::STATUS_ACKNOWLEDGED,
        ])->count();

        $resolvedCount = EmailIncident::where('status', EmailIncident::STATUS_RESOLVED)->count();

        return view('admin.super-admin.email-incidents.index', compact(
            'incidents',
            'openCount',
            'resolvedCount'
        ));
    }

    /**
     * Incident detail page.
     */
    public function show(int $incidentId): View
    {
        $incident = EmailIncident::with(['acknowledgedBy', 'resolvedBy'])
            ->findOrFail($incidentId);

        return view('admin.super-admin.email-incidents.show', compact('incident'));
    }

    /**
     * Mark an incident as acknowledged.
     */
    public function acknowledge(Request $request, int $incidentId): RedirectResponse
    {
        $incident = EmailIncident::findOrFail($incidentId);

        $admin = Auth::guard('admin')->user();
        $adminId = $admin instanceof Admin ? $admin->id : null;
        $adminEmail = $admin instanceof Admin ? $admin->email : 'unknown';

        $incident->update([
            'status' => EmailIncident::STATUS_ACKNOWLEDGED,
            'acknowledged_by_admin_id' => $adminId,
            'acknowledged_at' => now(),
        ]);

        $this->writeAudit('Acknowledged Incident', $incident, $adminId, $adminEmail);

        return back()->with('success', 'Incident acknowledged. It remains visible until resolved.');
    }

    /**
     * Mark an incident as resolved. This records that the incident has been
     * handled — it does NOT claim the original email was delivered.
     */
    public function resolve(Request $request, int $incidentId): RedirectResponse
    {
        $incident = EmailIncident::findOrFail($incidentId);

        $admin = Auth::guard('admin')->user();
        $adminId = $admin instanceof Admin ? $admin->id : null;
        $adminEmail = $admin instanceof Admin ? $admin->email : 'unknown';

        $incident->update([
            'status' => EmailIncident::STATUS_RESOLVED,
            'resolved_by_admin_id' => $adminId,
            'resolved_at' => now(),
        ]);

        $this->writeAudit('Resolved Incident', $incident, $adminId, $adminEmail);

        return back()->with('success', 'Incident marked as resolved.');
    }

    /**
     * DEVELOPMENT / TEST ONLY — simulate a definitive email dispatch failure.
     *
     * Uses the exact same EmailIncidentService path as a real failure, so it
     * produces: Laravel error log -> incident -> Super Admin notification.
     * It never touches production SMTP / mailgun credentials.
     *
     * Guarded by the `super.admin` middleware and, optionally, the
     * system_incidents.simulate_enabled config flag.
     */
    public function simulateFailure(Request $request): RedirectResponse
    {
        if (! config('system_incidents.simulate_enabled', true)) {
            return back()->withErrors([
                'simulate' => 'Email failure simulation is disabled in this environment.',
            ]);
        }

        $service = app(EmailIncidentService::class);

        $incident = $service->record(
            emailType: 'Test Email',
            errorMessage: 'SMTP connection failed (simulated for audit testing).',
            relatedRecordType: 'System',
            relatedRecordId: 'simulation',
        );

        if (! $incident) {
            return back()->with('error', 'Simulation could not be recorded. Check the application logs.');
        }

        $this->writeAudit(
            'Simulated Email Failure',
            $incident,
            Auth::guard('admin')->user() instanceof Admin ? Auth::guard('admin')->id() : null,
            Auth::guard('admin')->user() instanceof Admin ? Auth::guard('admin')->user()->email : 'unknown'
        );

        return redirect()
            ->route('admin.super-admin.email-incidents.show', $incident->incident_id)
            ->with('success', 'Simulated email failure recorded. An incident and Super Admin notification were created.');
    }

    private function writeAudit(string $action, EmailIncident $incident, ?int $adminId, string $adminEmail): void
    {
        AuditLog::create([
            'actor_type' => 'admin',
            'actor_id' => $adminId,
            'action' => $action,
            'module' => 'Email Incidents',
            'description' => $action . ' for email incident #' . $incident->incident_id
                . ' (' . $incident->email_type . ') by ' . $adminEmail . '.',
            'created_at' => now(),
        ]);
    }
}
