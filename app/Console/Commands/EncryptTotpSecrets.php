<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Encrypt any existing PLAINTEXT TOTP secrets in the auth tables.
 *
 * Why this is needed:
 *   The Admin / Security / User models now cast mfa_totp_secret as
 *   'encrypted'. Rows written BEFORE that cast contain plaintext Base32
 *   secrets. Laravel's encrypted cast expects the stored value to be an
 *   encrypted payload, so those legacy rows must be converted in place.
 *
 * Safety:
 *   - Only touches rows where the column is non-null and non-empty.
 *   - Detects values that are ALREADY encrypted (Laravel encrypted payloads
 *     are JSON objects with iv/value/mac) and leaves them untouched, so the
 *     command is idempotent and never double-encrypts.
 *   - Never logs or prints the secret.
 *   - NULL / empty values are left alone.
 *   - Can be run once in production after the models ship with the cast.
 */
class EncryptTotpSecrets extends Command
{
    protected $signature = 'mfa:encrypt-totp-secrets
        {--pretend : Report what would change without writing}';

    protected $description = 'Encrypt legacy plaintext TOTP secrets in admins/security/users_tbl (idempotent, never double-encrypts).';

    private const TABLES = [
        'admins' => 'id',
        'security' => 'id',
        'users_tbl' => 'user_id',
    ];

    public function handle(): int
    {
        $pretend = (bool) $this->option('pretend');
        $totalEncrypted = 0;
        $totalAlready = 0;
        $totalSkipped = 0;

        foreach (self::TABLES as $table => $keyColumn) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'mfa_totp_secret')) {
                $this->warn("Skipping {$table}: table/column not present.");

                continue;
            }

            $rows = DB::table($table)
                ->whereNotNull('mfa_totp_secret')
                ->where('mfa_totp_secret', '!=', '')
                ->get([$keyColumn, 'mfa_totp_secret']);

            foreach ($rows as $row) {
                $raw = (string) $row->mfa_totp_secret;

                if ($this->looksEncrypted($raw)) {
                    $totalAlready++;

                    continue;
                }

                if ($pretend) {
                    $this->line("[{$table}] #{$row->{$keyColumn}}: would encrypt (pretend)");
                    $totalEncrypted++;

                    continue;
                }

                try {
                    $encrypted = Crypt::encryptString($raw);

                    DB::table($table)
                        ->where($keyColumn, $row->{$keyColumn})
                        ->update(['mfa_totp_secret' => $encrypted]);

                    $totalEncrypted++;
                } catch (\Throwable $e) {
                    // Never log the secret; log only the row identifier + generic error.
                    $this->error("[{$table}] #{$row->{$keyColumn}}: encryption failed: " . $e->getMessage());

                    return self::FAILURE;
                }
            }
        }

        $this->info("Encrypted: {$totalEncrypted}");
        $this->info("Already encrypted (skipped): {$totalAlready}");
        $this->info("Null/empty (skipped): {$totalSkipped}");

        if ($totalEncrypted > 0 && ! $pretend) {
            $this->warn('Existing MFA secrets were encrypted. Users can continue logging in normally; no re-enrollment is required for these rows.');
        }

        return self::SUCCESS;
    }

    /**
     * Laravel 'encrypted' values are stored as base64(JSON{iv,value,mac[,tag]}).
     */
    private function looksEncrypted(string $value): bool
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return false;
        }

        $decoded = base64_decode($trimmed, true);

        if ($decoded === false || $decoded === '') {
            return false;
        }

        $payload = json_decode($decoded, true);

        return is_array($payload)
            && isset($payload['iv'], $payload['value'], $payload['mac']);
    }
}
