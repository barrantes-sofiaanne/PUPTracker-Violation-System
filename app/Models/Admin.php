<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Schema;

/**
 * @property int $id
 * @property string $username
 * @property string $email
 * @property string $password
 * @property string|null $remember_token
 * @property string|null $mfa_totp_secret
 * @property bool $mfa_totp_enabled
 * @property string|null $idp_subject
 * @property string|null $idp_email
 * @property \Illuminate\Support\Carbon|null $idp_connected_at
 */
class Admin extends Authenticatable
{
    protected $table = 'admins';

    public $timestamps = false;

    protected $fillable = [
        'username',
        'email',
        'password',
        'remember_token',
        'mfa_totp_secret',
        'mfa_totp_enabled',
        'mfa_backup_codes',
        'mfa_backup_codes_used',
        'idp_subject',
        'idp_email',
        'idp_connected_at',
    ];

    protected $hidden = [
        'password',
        'mfa_totp_secret',
    ];

    protected $casts = [
        'mfa_totp_secret' => 'encrypted',
        'mfa_totp_enabled' => 'boolean',
        'reset_token_expires_at' => 'datetime',
        'idp_connected_at' => 'datetime',
    ];

    public function getAuthPassword()
    {
        return $this->password;
    }

    public function recorder(): BelongsTo
{
    return $this->belongsTo(
        Admin::class,
        'recorder_id',
        'id'
    );
}
    public function adminInfo(): HasOne
{
    return $this->hasOne(
        AdminInfo::class,
        'admin_id',
        'id'
    );
}

    public function isItAdministrator(): bool
    {
        try {
            if (!Schema::hasTable('admin_info_tbl')) {
                return false;
            }

            $adminInfo = $this->adminInfo;

            $candidates = [
                $adminInfo?->position,
                $adminInfo?->Position,
                $adminInfo?->designation,
                $adminInfo?->role,
                $adminInfo?->title,
            ];

            foreach ($candidates as $candidate) {
                if (is_string($candidate) && strcasecmp(trim($candidate), 'IT Administrator') === 0) {
                    return true;
                }
            }

            return false;
        } catch (\Throwable $e) {
            // If there's any error checking admin info, default to false
            \Illuminate\Support\Facades\Log::warning('Error checking admin info', [
                'admin_id' => $this->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Resolve this administrator's assigned role NAME (e.g. "Admin" or
     * "IT Administrator").
     *
     * The role lives in `roles_tbl.roles_name`, reached from
     * `admin_info_tbl.role_id` (the same lookup the Admin → Users screen uses).
     * A legacy free-text role can also sit in the `role` column of
     * `admin_info_tbl`, which is only consulted when it exactly matches a known
     * role name — so an arbitrary string can never be mistaken for a role.
     *
     * Returns null when no role can be determined. Callers MUST treat null as a
     * denial (fail closed): guessing here would mean guessing at privilege.
     */
    public function roleName(): ?string
    {
        try {
            $adminInfo = $this->adminInfo;

            if ($adminInfo === null) {
                return null;
            }

            $known = ['admin', 'it administrator'];

            $roleNames = [];
            if (isset($adminInfo->role_id)) {
                $roleNames[] = Role::query()
                    ->whereKey($adminInfo->role_id)
                    ->value('roles_name');
            }

            // Legacy free-text fallback, accepted only when it IS a known role.
            $roleNames[] = $adminInfo->role ?? null;

            foreach ($roleNames as $roleName) {
                if (! is_string($roleName)) {
                    continue;
                }

                $roleName = trim($roleName);

                if (in_array(strtolower($roleName), $known, true)) {
                    return $roleName;
                }
            }

            return null;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Error resolving admin role', [
                'admin_id' => $this->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Whether this administrator holds the IT Administrator role.
     *
     * Checks the structured role first, then falls back to the legacy
     * position/designation/title scan in {@see isItAdministrator()}.
     */
    public function isItAdministratorRole(): bool
    {
        $roleName = $this->roleName();

        if ($roleName !== null) {
            return strcasecmp($roleName, 'IT Administrator') === 0;
        }

        return $this->isItAdministrator();
    }
}