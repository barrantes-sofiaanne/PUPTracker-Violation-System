<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Security;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * TOTP secret encryption.
 *
 * Verifies that mfa_totp_secret is stored encrypted (reversible cast), stays
 * hidden from serialization, and that the mfa:encrypt-totp-secrets command
 * converts legacy plaintext rows without double-encrypting.
 *
 * All secrets here are synthetic test values (never real production seeds).
 */
class TotpEncryptionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createAuthTables();
    }

    public function test_new_totp_secret_is_encrypted_in_database_but_readable_via_model(): void
    {
        $secret = 'JBSWY3DPEHPK3PXP'; // synthetic test secret

        $admin = Admin::create([
            'username' => 'totp-admin',
            'email' => 'totp-admin@example.com',
            'password' => Hash::make('secret'),
            'mfa_totp_secret' => $secret,
            'mfa_totp_enabled' => true,
        ]);

        // Raw DB value must NOT be the plaintext secret.
        $raw = DB::table('admins')->where('id', $admin->id)->value('mfa_totp_secret');
        $this->assertNotSame($secret, $raw);
        $this->assertNotContains($secret, [(string) $raw]);

        // The stored value is a Laravel encrypted payload (base64 of JSON).
        $payload = json_decode((string) base64_decode((string) $raw, true), true);
        $this->assertIsArray($payload);
        $this->assertArrayHasKey('iv', $payload);
        $this->assertArrayHasKey('value', $payload);
        $this->assertArrayHasKey('mac', $payload);

        // The model can retrieve/decrypt the secret.
        $admin->refresh();
        $this->assertSame($secret, $admin->mfa_totp_secret);
        $this->assertSame($secret, Crypt::decryptString((string) $raw));
    }

    public function test_totp_secret_is_hidden_from_serialized_output(): void
    {
        Admin::create([
            'username' => 'totp-hidden',
            'email' => 'totp-hidden@example.com',
            'password' => Hash::make('secret'),
            'mfa_totp_secret' => 'JBSWY3DPEHPK3PXP',
            'mfa_totp_enabled' => true,
        ]);

        $admin = Admin::where('email', 'totp-hidden@example.com')->firstOrFail();

        $json = $admin->toJson();
        $this->assertStringNotContainsString('JBSWY3DPEHPK3PXP', $json);
        $this->assertArrayNotHasKey('mfa_totp_secret', $admin->toArray());
    }

    public function test_security_and_student_totp_secrets_are_encrypted(): void
    {
        Security::create([
            'email' => 'totp-security@example.com',
            'password' => Hash::make('secret'),
            'mfa_totp_secret' => 'GEZDGNBVGY3TQOJQ',
            'mfa_totp_enabled' => true,
        ]);
        User::create([
            'student_number' => '2026-TOTP-1',
            'first_name' => 'T',
            'last_name' => 'User',
            'email' => 'totp-student@example.com',
            'password_hash' => Hash::make('secret'),
            'mfa_totp_secret' => 'MFRGGZDFMZTWQ2LK',
            'mfa_totp_enabled' => true,
        ]);

        $securityRaw = DB::table('security')->where('email', 'totp-security@example.com')->value('mfa_totp_secret');
        $userRaw = DB::table('users_tbl')->where('email', 'totp-student@example.com')->value('mfa_totp_secret');

        $this->assertNotSame('GEZDGNBVGY3TQOJQ', $securityRaw);
        $this->assertNotSame('MFRGGZDFMZTWQ2LK', $userRaw);

        $this->assertSame('GEZDGNBVGY3TQOJQ', Security::where('email', 'totp-security@example.com')->first()->mfa_totp_secret);
        $this->assertSame('MFRGGZDFMZTWQ2LK', User::where('email', 'totp-student@example.com')->first()->mfa_totp_secret);
    }

    public function test_command_encrypts_existing_plaintext_and_is_idempotent(): void
    {
        // Insert PLAINTEXT secrets directly (bypassing the model cast), as if
        // written before the encrypted cast existed.
        DB::table('admins')->insert([
            'username' => 'legacy-admin',
            'email' => 'legacy-admin@example.com',
            'password' => Hash::make('secret'),
            'mfa_totp_secret' => 'JBSWY3DPEHPK3PXP',
            'mfa_totp_enabled' => 1,
        ]);
        DB::table('security')->insert([
            'email' => 'legacy-security@example.com',
            'password' => Hash::make('secret'),
            'mfa_totp_secret' => 'GEZDGNBVGY3TQOJQ',
            'mfa_totp_enabled' => 1,
        ]);
        DB::table('users_tbl')->insert([
            'student_number' => '2026-LEGACY-1',
            'first_name' => 'L',
            'last_name' => 'User',
            'email' => 'legacy-student@example.com',
            'password_hash' => Hash::make('secret'),
            'mfa_totp_secret' => 'MFRGGZDFMZTWQ2LK',
            'mfa_totp_enabled' => 1,
        ]);

        $exit = Artisan::call('mfa:encrypt-totp-secrets');
        $this->assertSame(0, $exit);

        // Raw values are now encrypted.
        $adminRaw = DB::table('admins')->where('email', 'legacy-admin@example.com')->value('mfa_totp_secret');
        $securityRaw = DB::table('security')->where('email', 'legacy-security@example.com')->value('mfa_totp_secret');
        $userRaw = DB::table('users_tbl')->where('email', 'legacy-student@example.com')->value('mfa_totp_secret');

        $this->assertNotSame('JBSWY3DPEHPK3PXP', $adminRaw);
        $this->assertNotSame('GEZDGNBVGY3TQOJQ', $securityRaw);
        $this->assertNotSame('MFRGGZDFMZTWQ2LK', $userRaw);

        // Decryptable back to the original.
        $this->assertSame('JBSWY3DPEHPK3PXP', Crypt::decryptString((string) $adminRaw));
        $this->assertSame('GEZDGNBVGY3TQOJQ', Crypt::decryptString((string) $securityRaw));
        $this->assertSame('MFRGGZDFMZTWQ2LK', Crypt::decryptString((string) $userRaw));

        // Second run must NOT double-encrypt.
        $exit2 = Artisan::call('mfa:encrypt-totp-secrets');
        $this->assertSame(0, $exit2);

        $adminRaw2 = DB::table('admins')->where('email', 'legacy-admin@example.com')->value('mfa_totp_secret');
        $this->assertSame($adminRaw, $adminRaw2);
        $this->assertSame('JBSWY3DPEHPK3PXP', Crypt::decryptString((string) $adminRaw2));
    }

    public function test_command_leaves_null_and_empty_secrets_untouched(): void
    {
        DB::table('admins')->insert([
            'username' => 'null-admin',
            'email' => 'null-admin@example.com',
            'password' => Hash::make('secret'),
            'mfa_totp_secret' => null,
            'mfa_totp_enabled' => 0,
        ]);
        DB::table('admins')->insert([
            'username' => 'empty-admin',
            'email' => 'empty-admin@example.com',
            'password' => Hash::make('secret'),
            'mfa_totp_secret' => '',
            'mfa_totp_enabled' => 0,
        ]);

        $exit = Artisan::call('mfa:encrypt-totp-secrets');
        $this->assertSame(0, $exit);

        $this->assertNull(DB::table('admins')->where('email', 'null-admin@example.com')->value('mfa_totp_secret'));
        $this->assertSame('', DB::table('admins')->where('email', 'empty-admin@example.com')->value('mfa_totp_secret'));
    }

    private function createAuthTables(): void
    {
        foreach ([
            'users_tbl' => function (Blueprint $table): void {
                $table->id('user_id');
                $table->string('student_number')->nullable()->unique();
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('email')->nullable();
                $table->string('password_hash')->nullable();
                $table->text('mfa_totp_secret')->nullable();
                $table->boolean('mfa_totp_enabled')->default(false);
                $table->timestamps();
            },
            'admins' => function (Blueprint $table): void {
                $table->id();
                $table->string('username')->nullable();
                $table->string('email')->unique();
                $table->string('password');
                $table->text('mfa_totp_secret')->nullable();
                $table->boolean('mfa_totp_enabled')->default(false);
            },
            'security' => function (Blueprint $table): void {
                $table->id();
                $table->string('email')->unique();
                $table->string('password');
                $table->text('mfa_totp_secret')->nullable();
                $table->boolean('mfa_totp_enabled')->default(false);
            },
        ] as $table => $closure) {
            if (! Schema::hasTable($table)) {
                Schema::create($table, $closure);
            }
        }
    }
}
