<?php

namespace App\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

class MfaSchema
{
    public static function supportsBackupCodes(Authenticatable|Model $user): bool
    {
        if (!$user instanceof Model) {
            return false;
        }

        $table = $user->getTable();
        $schema = $user->getConnection()->getSchemaBuilder();

        return $schema->hasTable($table)
            && $schema->hasColumn($table, 'mfa_backup_codes')
            && $schema->hasColumn($table, 'mfa_backup_codes_used');
    }

    public static function isMissingColumnException(\Throwable $exception): bool
    {
        $message = $exception->getMessage();

        return str_contains($message, 'Unknown column')
            || str_contains($message, 'doesn\'t have a column')
            || str_contains($message, 'SQLSTATE[42S22]');
    }

    public static function forgetBackupCodeAttributes(Model $user): void
    {
        $user->offsetUnset('mfa_backup_codes');
        $user->offsetUnset('mfa_backup_codes_used');
    }
}
