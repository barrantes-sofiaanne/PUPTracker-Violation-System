<?php

namespace App\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class MfaSchema
{
    public static function supportsBackupCodes(Authenticatable $user): bool
    {
        if (!$user instanceof Model) {
            return false;
        }

        $table = $user->getTable();

        return Schema::hasTable($table)
            && Schema::hasColumn($table, 'mfa_backup_codes')
            && Schema::hasColumn($table, 'mfa_backup_codes_used');
    }
}
