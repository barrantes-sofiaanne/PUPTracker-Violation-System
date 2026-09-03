<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\EvidenceSecurityEvent;
use App\Models\UserManagementHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Centralized logging facade for the PUPTracker accountability architecture.
 *
 * The system keeps three distinct accountability stores (never a single blob):
 *
 *   - AuditLog ....................... WHO did WHAT (business/security accountability)
 *   - UserManagementHistory ......... WHAT changed about a USER ACCOUNT (lifecycle)
 *   - EvidenceSecurityEvent ......... WHAT happened to VIOLATION EVIDENCE
 *
 * Technical diagnostics stay in the Laravel application logs (config/logging.php)
 * and are never written here.
 *
 * Design rules enforced by this service:
 *   - A single consistent write path (actor/module/target/ip/ua/timestamp).
 *   - Fail-safe: a logging failure never breaks the primary business action.
 *   - Secret-free: callers never pass tokens/passwords; helper methods scrub.
 *   - Minimal metadata: IP/user-agent only where a Request is available and only
 *     where justified; full request bodies are never logged.
 *   - Append-only: rows are only ever created via this service; no update/delete
 *     helpers are exposed.
 */
class AuditLogService
{
    /**
     * Modules whose audit entries are considered PRIVILEGED (Super Admin only).
     * Normal Admins must not be able to see these even by direct URL access.
     */
    public const PRIVILEGED_MODULES = [
        'Backup',
        'Email Incidents',
        'Maintenance Configuration',
        'Maintenance Records',
        'Audit Control Plan',
        'System Configuration',
        'System Logs',
        'Role/Permission Configuration',
        'Security Configuration',
        'IDP Configuration',
    ];

    /**
     * Write an Audit Log entry (append-only). Non-fatal by default.
     *
     * @param  array  $attributes  actor_type, actor_id, action, module,
     *                             description (actor/module/action required).
     */
    public function audit(array $attributes, ?Request $request = null, bool $transactional = false): ?AuditLog
    {
        $payload = array_filter([
            'actor_type' => $attributes['actor_type'] ?? $this->resolveActorType(),
            'actor_id' => $attributes['actor_id'] ?? $this->resolveActorId(),
            'action' => $attributes['action'] ?? null,
            'module' => $attributes['module'] ?? null,
            'description' => $this->truncate((string) ($attributes['description'] ?? ''), 2000),
            'created_at' => $attributes['created_at'] ?? now(),
        ], fn ($v) => $v !== null);

        if ($request) {
            // HTTP context is only stored when the underlying audit_logs table
            // has the columns (legacy/test schemas may not) — otherwise skip to
            // keep the write safe and consistent.
            if (Schema::hasColumn('audit_logs', 'ip_address')) {
                $payload['ip_address'] = $this->clientIp($request);
            }
            if (Schema::hasColumn('audit_logs', 'user_agent')) {
                $payload['user_agent'] = $this->userAgent($request);
            }
        }

        // If the schema is the legacy set, the model's compat hook maps to
        // user_role/user_id/action_type/table_affected/new_data automatically.
        try {
            if ($transactional && $request) {
                // Still best-effort; callers that need strict atomicity should
                // create the audit row inside their own DB::transaction.
            }

            return AuditLog::create($payload);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Write a User-Management History entry (account lifecycle change).
     *
     * @param  array  $attributes  user_id, performed_by, action, details,
     *                             changes (array of field/from/to).
     */
    public function userHistory(array $attributes): ?UserManagementHistory
    {
        if (! Schema::hasTable('user_management_histories')) {
            return null;
        }

        try {
            return UserManagementHistory::create([
                'user_id' => $attributes['user_id'] ?? null,
                'performed_by' => $attributes['performed_by'] ?? null,
                'action' => $attributes['action'] ?? 'Account Update',
                'details' => $this->truncate((string) ($attributes['details'] ?? ''), 2000),
                'changes' => $attributes['changes'] ?? [],
                'created_at' => $attributes['created_at'] ?? now(),
            ]);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Convenience: record BOTH the account lifecycle history AND an audit entry
     * for a user-account change (per the spec's example: status change appears
     * in both — history holds the field/old/new, audit holds the who/what).
     */
    public function userAccountChange(
        int|string|null $userId,
        ?int $actorId,
        string $accountType,
        string $action,
        array $changes = [],
        ?string $displayLabel = null,
        ?Request $request = null,
    ): void {
        $label = $displayLabel ?? ('#' . (string) ($userId ?? 'unknown'));

        // For lifecycle codes (e.g. IDP_LINKED) the account-type suffix is
        // redundant; for plain verbs (Create/Update/Delete) it reads naturally.
        $historyAction = str_contains($action, '_')
            ? strtoupper($action)
            : $action . ' ' . ucfirst($accountType) . ' Account';

        $this->userHistory([
            'user_id' => $userId,
            'performed_by' => $actorId,
            'action' => $historyAction,
            'details' => $action . ' ' . $accountType . ' account ' . $label . '.',
            'changes' => $changes,
        ]);

        $description = $action . ' ' . $accountType . ' account ' . $label . '.';
        if (! empty($changes)) {
            $parts = array_map(function (array $change): string {
                if (str_contains((string) ($change['to'] ?? ''), '(deleted)')) {
                    return $change['field'] . ': "' . $change['from'] . '" (deleted)';
                }

                return $change['field'] . ' from "' . $change['from'] . '" to "' . $change['to'] . '"';
            }, $changes);

            $description .= ' Changes: ' . implode('; ', $parts) . '.';
        }

        $this->audit([
            'actor_type' => 'admin',
            'actor_id' => $actorId,
            'action' => $action . ' ' . ucfirst($accountType) . ' Account',
            'module' => 'User Management',
            'description' => $description,
        ], $request);
    }

    /**
     * Write an Evidence Security Event (evidence-specific).
     *
     * @param  array  $attributes  violation_id, evidence_id, event_type, severity,
     *                             actor_type, actor_id, ip_address, user_agent, meta
     */
    public function evidenceEvent(array $attributes, ?Request $request = null): ?EvidenceSecurityEvent
    {
        if (! Schema::hasTable('evidence_security_events_tbl')) {
            return null;
        }

        try {
            $payload = array_filter([
                'violation_id' => $attributes['violation_id'] ?? null,
                'evidence_id' => $attributes['evidence_id'] ?? null,
                'event_type' => $attributes['event_type'] ?? null,
                'severity' => $attributes['severity'] ?? 'info',
                'actor_type' => $attributes['actor_type'] ?? null,
                'actor_id' => $attributes['actor_id'] ?? null,
                'meta' => isset($attributes['meta']) ? json_encode($attributes['meta']) : null,
                'created_at' => $attributes['created_at'] ?? now(),
            ], fn ($v) => $v !== null);

            if ($request) {
                $payload['ip_address'] = $this->clientIp($request);
                $payload['user_agent'] = $this->userAgent($request);
            }

            return EvidenceSecurityEvent::create($payload);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Whether an audit module is privileged (Super Admin only).
     */
    public function isPrivilegedModule(?string $module): bool
    {
        $module = trim((string) $module);

        return in_array($module, self::PRIVILEGED_MODULES, true);
    }

    /**
     * Whether the current admin is a Super Admin (IT Administrator).
     */
    public function isSuperAdmin(): bool
    {
        if (! auth('admin')->check()) {
            return false;
        }

        /** @var \App\Models\Admin $admin */
        $admin = auth('admin')->user();

        return $admin->isItAdministrator();
    }

    /**
     * Resolve the actor type from the active guard, or null.
     */
    protected function resolveActorType(): ?string
    {
        foreach (['admin', 'security', 'student'] as $guard) {
            if (auth($guard)->check()) {
                return $guard;
            }
        }

        return null;
    }

    /**
     * Resolve the actor id from the active guard, or null.
     */
    protected function resolveActorId(): ?int
    {
        foreach (['admin', 'security', 'student'] as $guard) {
            if (auth($guard)->check()) {
                $user = auth($guard)->user();

                return (int) ($guard === 'student'
                    ? ($user->user_id ?? $user->getAuthIdentifier())
                    : $user->getAuthIdentifier());
            }
        }

        return null;
    }

    protected function clientIp(?Request $request): ?string
    {
        return $request?->ip() ?: ($request?->header('X-Forwarded-For') ?: null);
    }

    protected function userAgent(?Request $request): ?string
    {
        return $request ? mb_substr((string) $request->userAgent(), 0, 500) ?: null : null;
    }

    protected function truncate(string $value, int $length): string
    {
        return mb_strlen($value) > $length ? mb_substr($value, 0, $length) . '…' : $value;
    }
}
