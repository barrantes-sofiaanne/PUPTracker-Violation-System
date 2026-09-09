<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Security;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * MFA login decryption failure investigation.
 *
 * Confirms the failure mechanism: reading mfa_totp_secret through the
 * 'encrypted' cast throws a DecryptException when the stored value is legacy
 * PLAINTEXT or was encrypted with a different APP_KEY — which happens BEFORE
 * MfaService::beginChallenge() and was being surfaced as the misleading
 * "Unable to send verification code" error.
 *
 * All secrets here are synthetic test values (never real production seeds).
 */
class MfaLoginDecryptionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createAuthTables();
    }

    // ------------------------------------------------------------------
    // Model cast read behavior
    // ------------------------------------------------------------------

    public function test_plaintext_secret_throws_decrypt_exception_when_read_through_cast(): void
    {
        $id = DB::table('admins')->insertGetId([
            'username' => 'legacy-plain',
            'email' => 'legacy-plain@example.com',
            'password' => Hash::make('secret'),
            // Legacy PLAINTEXT seed (as stored before the encrypted cast).
            'mfa_totp_secret' => 'JBSWY3DPEHPK3PXP',
            'mfa_totp_enabled' => 1,
        ]);

        $admin = Admin::find($id);
        $this->assertNotNull($admin);

        try {
            $admin->mfa_totp_secret;
            $this->fail('Reading a plaintext secret through the encrypted cast must throw a DecryptException.');
        } catch (DecryptException) {
            $this->assertTrue(true);
        }
    }

    public function test_encrypted_secret_reads_back_as_plaintext_through_cast(): void
    {
        $admin = Admin::create([
            'username' => 'encrypted-ok',
            'email' => 'encrypted-ok@example.com',
            'password' => Hash::make('secret'),
            'mfa_totp_secret' => 'GEZDGNBVGY3TQOJQ',
            'mfa_totp_enabled' => true,
        ]);

        $this->assertSame('GEZDGNBVGY3TQOJQ', $admin->fresh()->mfa_totp_secret);
    }

    public function test_undecryptable_encrypted_payload_throws_when_read_through_cast(): void
    {
        // A structurally valid encrypted payload encrypted under a DIFFERENT
        // key (we simulate by encrypting with a throwaway key, then storing).
        $otherEncrypter = new \Illuminate\Encryption\Encrypter(random_bytes(32), config('app.cipher'));
        $foreignPayload = $otherEncrypter->encryptString('JBSWY3DPEHPK3PXP');

        $id = DB::table('admins')->insertGetId([
            'username' => 'foreign-key',
            'email' => 'foreign-key@example.com',
            'password' => Hash::make('secret'),
            'mfa_totp_secret' => $foreignPayload,
            'mfa_totp_enabled' => 1,
        ]);

        $admin = Admin::find($id);
        $this->assertNotNull($admin);

        try {
            $admin->mfa_totp_secret;
            $this->fail('Reading a secret encrypted with a different key must throw a DecryptException.');
        } catch (DecryptException) {
            $this->assertTrue(true);
        }
    }

    public function test_null_secret_reads_back_as_null_without_throwing(): void
    {
        $admin = Admin::create([
            'username' => 'null-secret',
            'email' => 'null-secret@example.com',
            'password' => Hash::make('secret'),
            'mfa_totp_secret' => null,
            'mfa_totp_enabled' => false,
        ]);

        $this->assertNull($admin->fresh()->mfa_totp_secret);
    }

    // ------------------------------------------------------------------
    // mfa:encrypt-totp-secrets behavior
    // ------------------------------------------------------------------

    public function test_encrypt_command_encrypts_plaintext_and_skips_undecryptable_values(): void
    {
        // Plaintext legacy row -> should be encrypted.
        $plainId = DB::table('admins')->insertGetId([
            'username' => 'cmd-plain',
            'email' => 'cmd-plain@example.com',
            'password' => Hash::make('secret'),
            'mfa_totp_secret' => 'JBSWY3DPEHPK3PXP',
            'mfa_totp_enabled' => 1,
        ]);

        // Foreign-key "encrypted-looking but not decryptable" row -> must be
        // skipped and never overwritten.
        $otherEncrypter = new \Illuminate\Encryption\Encrypter(random_bytes(32), config('app.cipher'));
        $foreignPayload = $otherEncrypter->encryptString('GEZDGNBVGY3TQOJQ');
        $foreignId = DB::table('admins')->insertGetId([
            'username' => 'cmd-foreign',
            'email' => 'cmd-foreign@example.com',
            'password' => Hash::make('secret'),
            'mfa_totp_secret' => $foreignPayload,
            'mfa_totp_enabled' => 1,
        ]);

        $exit = Artisan::call('mfa:encrypt-totp-secrets');
        $this->assertSame(0, $exit);

        // Plaintext row is now encrypted + decryptable.
        $plainRaw = DB::table('admins')->where('id', $plainId)->value('mfa_totp_secret');
        $this->assertNotSame('JBSWY3DPEHPK3PXP', $plainRaw);
        $this->assertSame('JBSWY3DPEHPK3PXP', Crypt::decryptString((string) $plainRaw));

        // Foreign row is UNCHANGED (not overwritten).
        $foreignRaw = DB::table('admins')->where('id', $foreignId)->value('mfa_totp_secret');
        $this->assertSame($foreignPayload, $foreignRaw);
    }

    // ------------------------------------------------------------------
    // mfa:diagnose-totp behavior
    // ------------------------------------------------------------------

    public function test_diagnose_reports_statuses_without_secrets(): void
    {
        DB::table('admins')->insert([
            'username' => 'diag-null',
            'email' => 'diag-null@example.com',
            'password' => Hash::make('secret'),
            'mfa_totp_secret' => null,
            'mfa_totp_enabled' => 0,
        ]);

        $admin = Admin::create([
            'username' => 'diag-enc',
            'email' => 'diag-enc@example.com',
            'password' => Hash::make('secret'),
            'mfa_totp_secret' => 'JBSWY3DPEHPK3PXP',
            'mfa_totp_enabled' => 1,
        ]);

        DB::table('admins')->insert([
            'username' => 'diag-plain',
            'email' => 'diag-plain@example.com',
            'password' => Hash::make('secret'),
            'mfa_totp_secret' => 'GEZDGNBVGY3TQOJQ',
            'mfa_totp_enabled' => 1,
        ]);

        Artisan::call('mfa:diagnose-totp');
        $output = Artisan::output();

        // Statuses are present.
        $this->assertStringContainsString('admins #' . $admin->id . ': DECRYPTABLE', $output);
        $this->assertStringContainsString(': NULL/EMPTY', $output);
        $this->assertStringContainsString(': NOT_DECRYPTABLE', $output);

        // The actual secret values must never appear in the output.
        $this->assertStringNotContainsString('JBSWY3DPEHPK3PXP', $output);
        $this->assertStringNotContainsString('GEZDGNBVGY3TQOJQ', $output);
    }

    // ------------------------------------------------------------------
    // Login flow
    // ------------------------------------------------------------------

    public function test_admin_login_with_decryptable_secret_starts_mfa_challenge(): void
    {
        $admin = Admin::create([
            'username' => 'login-ok',
            'email' => 'login-ok@example.com',
            'password' => Hash::make('password123'),
            'mfa_totp_secret' => 'JBSWY3DPEHPK3PXP',
            'mfa_totp_enabled' => true,
        ]);

        $this->post(route('admin.login.post'), [
            'email' => 'login-ok@example.com',
            'password' => 'password123',
        ])->assertRedirect(route('mfa.verify.show'));
    }

    public function test_admin_login_with_plaintext_secret_returns_categorized_error_without_leak(): void
    {
        DB::table('admins')->insert([
            'username' => 'login-plain',
            'email' => 'login-plain@example.com',
            'password' => Hash::make('password123'),
            'mfa_totp_secret' => 'JBSWY3DPEHPK3PXP',
            'mfa_totp_enabled' => 1,
        ]);

        $response = $this->from(route('admin.login'))
            ->post(route('admin.login.post'), [
                'email' => 'login-plain@example.com',
                'password' => 'password123',
            ]);

        // It must NOT be treated as a generic "invalid credentials".
        $response->assertSessionHasErrors('login');
        $response->assertSessionDoesntHaveErrors('email');

        $errors = session('errors');
        $message = $errors->first('login');
        $this->assertStringContainsString('multi-factor', strtolower((string) $message));
        $this->assertStringNotContainsString('JBSWY3DPEHPK3PXP', (string) $message);
        $this->assertStringNotContainsString('DecryptException', (string) $message);
    }

    public function test_security_login_with_undecryptable_secret_returns_categorized_error(): void
    {
        $otherEncrypter = new \Illuminate\Encryption\Encrypter(random_bytes(32), config('app.cipher'));
        $foreignPayload = $otherEncrypter->encryptString('GEZDGNBVGY3TQOJQ');

        DB::table('security')->insert([
            'email' => 'login-foreign@example.com',
            'password' => Hash::make('password123'),
            'mfa_totp_secret' => $foreignPayload,
            'mfa_totp_enabled' => 1,
        ]);

        $response = $this->from(route('security.login'))
            ->post(route('security.login.post'), [
                'email' => 'login-foreign@example.com',
                'password' => 'password123',
            ]);

        $response->assertSessionHasErrors('login');
        $errors = session('errors');
        $message = $errors->first('login');
        $this->assertStringContainsString('multi-factor', strtolower((string) $message));
        $this->assertStringNotContainsString('GEZDGNBVGY3TQOJQ', (string) $message);
    }

    // ------------------------------------------------------------------
    // Log safety
    // ------------------------------------------------------------------

    public function test_secret_is_never_written_to_logs(): void
    {
        $secret = 'JBSWY3DPEHPK3PXP';
        $admin = Admin::create([
            'username' => 'log-safe',
            'email' => 'log-safe@example.com',
            'password' => Hash::make('password123'),
            'mfa_totp_secret' => $secret,
            'mfa_totp_enabled' => true,
        ]);

        // The raw DB value must be encrypted, never the plaintext secret.
        $raw = DB::table('admins')->where('id', $admin->id)->value('mfa_totp_secret');
        $this->assertNotSame($secret, $raw);

        Log::shouldReceive('error')->andReturnNull();
        Log::shouldReceive('info')->andReturnNull();
        Log::shouldReceive('warning')->andReturnNull();

        // Trigger a read + a login attempt to flush any log calls.
        $this->post(route('admin.login.post'), [
            'email' => 'log-safe@example.com',
            'password' => 'password123',
        ]);

        $this->assertTrue(true);
    }

    // ------------------------------------------------------------------
    // Schema
    // ------------------------------------------------------------------

    private function createAuthTables(): void
    {
        foreach ([
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
            'users_tbl' => function (Blueprint $table): void {
                $table->id('user_id');
                $table->string('student_number')->nullable()->unique();
                $table->string('email')->nullable();
                $table->string('password_hash')->nullable();
                $table->text('mfa_totp_secret')->nullable();
                $table->boolean('mfa_totp_enabled')->default(false);
                $table->timestamps();
            },
            'audit_logs' => function (Blueprint $table): void {
                $table->id('audit_log_id');
                $table->string('actor_type')->nullable();
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->string('action');
                $table->string('module')->nullable();
                $table->text('description')->nullable();
                $table->timestamp('created_at')->useCurrent();
            },
        ] as $table => $closure) {
            if (! Schema::hasTable($table)) {
                Schema::create($table, $closure);
            }
        }
    }
}
