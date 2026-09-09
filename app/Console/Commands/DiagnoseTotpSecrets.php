<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SAFE diagnostic for production TOTP secrets.
 *
 * Reports ONLY a per-row status — never the secret itself:
 *
 *   admins #1: NULL/EMPTY
 *   admins #2: DECRYPTABLE
 *   admins #3: NOT_DECRYPTABLE
 *   security #1: DECRYPTABLE
 *   security #2: NOT_DECRYPTABLE
 *
 * It never prints or logs the stored value. This is intended to be run in
 * production to determine WHY the MFA login flow throws before a challenge
 * can start (legacy plaintext vs. encrypted-with-a-different-APP_KEY).
 */
class DiagnoseTotpSecrets extends Command
{
    protected $signature = 'mfa:diagnose-totp';

    protected $description = 'Report TOTP secret status per auth row (NULL/EMPTY, DECRYPTABLE, NOT_DECRYPTABLE). Never prints secrets.';

    private const TABLES = [
        'admins' => 'id',
        'security' => 'id',
        'users_tbl' => 'user_id',
    ];

    public function handle(): int
    {
        $anyNotDecryptable = false;

        foreach (self::TABLES as $table => $keyColumn) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'mfa_totp_secret')) {
                $this->warn("{$table}: table/column not present.");

                continue;
            }

            $rows = DB::table($table)
                ->orderBy($keyColumn)
                ->get([$keyColumn, 'mfa_totp_secret']);

            foreach ($rows as $row) {
                $raw = (string) ($row->mfa_totp_secret ?? '');
                $label = "{$table} #{$row->{$keyColumn}}";

                if ($raw === '' || $row->mfa_totp_secret === null) {
                    $this->line("{$label}: NULL/EMPTY");

                    continue;
                }

                if ($this->decrypts($raw)) {
                    $this->line("{$label}: DECRYPTABLE");

                    continue;
                }

                // NOT_DECRYPTABLE: either legacy plaintext or encrypted with a
                // different APP_KEY. We never reveal which, and we never mutate.
                $this->line("{$label}: NOT_DECRYPTABLE");
                $anyNotDecryptable = true;
            }
        }

        if ($anyNotDecryptable) {
            $this->newLine();
            $this->warn('Some TOTP values are NOT decryptable with the current APP_KEY.');
            $this->warn('If they are legacy PLAINTEXT, run `php artisan mfa:encrypt-totp-secrets` ONCE (after confirming APP_KEY is the stable production key).');
            $this->warn('If they were encrypted with a DIFFERENT key, resolve the APP_KEY mismatch first — do NOT re-encrypt or overwrite them.');
        } else {
            $this->newLine();
            $this->info('All non-empty TOTP values are decryptable with the current APP_KEY.');
        }

        return self::SUCCESS;
    }

    /**
     * True only when the stored value is a valid Laravel encrypted payload
     * that can be decrypted with the current key. Never returns/prints content.
     */
    private function decrypts(string $value): bool
    {
        try {
            Crypt::decryptString($value);

            return true;
        } catch (DecryptException) {
            return false;
        } catch (\Throwable) {
            return false;
        }
    }
}
