<?php

namespace App\Support\Auth;

use App\Models\Admin;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StateAwareUserProvider extends EloquentUserProvider
{
    public function retrieveByToken($identifier, #[\SensitiveParameter] $token)
    {
        $user = parent::retrieveByToken($identifier, $token);

        return $user !== null && $this->accountMayUseRememberMe($user)
            ? $user
            : null;
    }

    public function updateRememberToken(
        Authenticatable $user,
        #[\SensitiveParameter] $token
    ): void {
        if (! $this->accountMayUseRememberMe($user)) {
            return;
        }

        parent::updateRememberToken($user, $token);
    }

    private function accountMayUseRememberMe(Authenticatable $user): bool
    {
        if (! $user instanceof Model) {
            return false;
        }

        if (method_exists($user, 'isActiveAccount') && ! $user->isActiveAccount()) {
            return false;
        }

        if ($user instanceof Admin && Schema::hasTable('admin_info_tbl')) {
            $adminInfo = $user->adminInfo;

            if ($adminInfo !== null && Schema::hasTable('roles_tbl') && $user->roleName() === null) {
                return false;
            }
        }

        if (
            Schema::hasColumn($user->getTable(), 'status_id')
            && $user->getAttribute('status_id') !== null
        ) {
            $statusIsActive = Schema::hasTable('status_tbl')
                && DB::table('status_tbl')
                    ->where('status_id', $user->getAttribute('status_id'))
                    ->whereRaw('LOWER(TRIM(status_name)) = ?', ['active'])
                    ->exists();

            if (! $statusIsActive && (int) $user->getAttribute('status_id') !== 1) {
                return false;
            }
        }

        return true;
    }
}
