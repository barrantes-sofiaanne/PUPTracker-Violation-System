<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\Security;
use App\Models\User;
use App\Support\TotpService;
use App\Support\MfaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

class MfaFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureAuthTablesForTests();
    }

    public function test_student_login_redirects_to_mfa_and_verifies_email_otp(): void
    {
        User::create([
            'student_number' => '2026-00001',
            'first_name' => 'Test',
            'last_name' => 'Student',
            'email' => 'student@example.com',
            'status_id' => 1,
            'password_hash' => Hash::make('password123'),
            'mfa_totp_enabled' => false,
        ]);

        $response = $this->post(route('student.login.post'), [
            'student_number' => '2026-00001',
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('mfa.verify.show'));
        $response->assertSessionHas('mfa.pending');

        $pending = session('mfa.pending');
        $pending['code_hash'] = Hash::make('123456');

        $verify = $this->withSession(['mfa.pending' => $pending])
            ->post(route('mfa.verify.submit'), [
                'method' => 'email',
                'code' => '123456',
            ]);

        $verify->assertRedirect(route('student.dashboard'));
        $this->assertAuthenticated('student');
    }

    public function test_admin_login_supports_totp_verification(): void
    {
        $secret = 'JBSWY3DPEHPK3PXP';

        Admin::create([
            'username' => 'mfa-admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('adminpass'),
            'mfa_totp_secret' => $secret,
            'mfa_totp_enabled' => true,
        ]);

        $response = $this->post(route('admin.login.post'), [
            'email' => 'admin@example.com',
            'password' => 'adminpass',
        ]);

        $response->assertRedirect(route('mfa.verify.show'));

        $pending = session('mfa.pending');
        $this->assertContains('totp', $pending['methods']);

        $totpCode = $this->generateCurrentTotp($secret);

        $verify = $this->withSession(['mfa.pending' => $pending])
            ->post(route('mfa.verify.submit'), [
                'method' => 'totp',
                'code' => $totpCode,
            ]);

        $verify->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticated('admin');
    }

    public function test_security_mfa_events_are_written_to_audit_log(): void
    {
        Security::create([
            'email' => 'security@example.com',
            'password' => Hash::make('securepass'),
            'mfa_totp_enabled' => false,
        ]);

        $login = $this->post(route('security.login.post'), [
            'email' => 'security@example.com',
            'password' => 'securepass',
        ]);

        $login->assertRedirect(route('mfa.verify.show'));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'mfa.challenge.started',
            'actor_type' => 'security',
        ]);

        $pending = session('mfa.pending');

        $this->withSession(['mfa.pending' => $pending])
            ->post(route('mfa.verify.submit'), [
                'method' => 'email',
                'code' => '000000',
            ])
            ->assertSessionHasErrors('code');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'mfa.verify.failed',
            'actor_type' => 'security',
        ]);

        $pending = session('mfa.pending');

        $this->withSession(['mfa.pending' => $pending])
            ->post(route('mfa.verify.cancel'))
            ->assertRedirect(route('security.login'));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'mfa.challenge.cancelled',
            'actor_type' => 'security',
        ]);
    }

    public function test_totp_setup_enables_totp_without_crashing_when_backup_code_columns_are_missing(): void
    {
        $admin = Admin::create([
            'username' => 'schema-admin',
            'email' => 'schema-admin@example.com',
            'password' => Hash::make('adminpass'),
            'mfa_totp_enabled' => false,
        ]);

        $secret = app(TotpService::class)->generateSecret();

        $response = $this->actingAs($admin, 'admin')
            ->withSession(['totp_setup_secret_admin' => $secret])
            ->post(route('totp.verify'), [
                'guard' => 'admin',
                'totp_code' => $this->generateCurrentTotp($secret),
            ]);

        $response->assertRedirect(route('admin.dashboard'));
        $response->assertSessionHas('warning');

        $admin->refresh();

        $this->assertTrue($admin->mfa_totp_enabled);
        $this->assertSame($secret, $admin->mfa_totp_secret);
    }

    public function test_student_login_always_requires_mfa(): void
    {
        User::create([
            'student_number' => '2026-00002',
            'first_name' => 'Regular',
            'last_name' => 'Student',
            'email' => 'regular-student@example.com',
            'status_id' => 1,
            'password_hash' => Hash::make('password123'),
            'mfa_totp_enabled' => false,
        ]);

        $response = $this->post(route('student.login.post'), [
            'student_number' => '2026-00002',
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('mfa.verify.show'));
        $response->assertSessionHas('mfa.pending');
        $this->assertGuest('student');
    }

    private function ensureAuthTablesForTests(): void
    {
        if (!Schema::hasTable('users_tbl')) {
            Schema::create('users_tbl', function (Blueprint $table): void {
                $table->id('user_id');
                $table->string('student_number')->unique();
                $table->string('first_name')->nullable();
                $table->string('middle_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('email')->nullable();
                $table->unsignedBigInteger('gender_id')->nullable();
                $table->unsignedBigInteger('status_id')->nullable();
                $table->unsignedBigInteger('roles_id')->nullable();
                $table->string('password_hash');
                $table->string('reset_token_hash')->nullable();
                $table->timestamp('reset_token_expires_at')->nullable();
                $table->timestamp('new_until')->nullable();
                $table->text('mfa_totp_secret')->nullable();
                $table->boolean('mfa_totp_enabled')->default(false);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('admins')) {
            Schema::create('admins', function (Blueprint $table): void {
                $table->id();
                $table->string('username')->nullable();
                $table->string('email')->unique();
                $table->string('password');
                $table->text('mfa_totp_secret')->nullable();
                $table->boolean('mfa_totp_enabled')->default(false);
            });
        }

        if (!Schema::hasTable('security')) {
            Schema::create('security', function (Blueprint $table): void {
                $table->id();
                $table->string('email')->unique();
                $table->string('password');
                $table->text('mfa_totp_secret')->nullable();
                $table->boolean('mfa_totp_enabled')->default(false);
            });
        }

        if (!Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $table): void {
                $table->id('audit_log_id');
                $table->string('actor_type')->nullable();
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->string('action');
                $table->string('module')->nullable();
                $table->text('description')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    private function generateCurrentTotp(string $secret): string
    {
        $decodedSecret = $this->base32Decode($secret);
        $counter = (int) floor(time() / 30);
        $counterBytes = pack('N*', 0) . pack('N*', $counter);
        $hash = hash_hmac('sha1', $counterBytes, $decodedSecret, true);
        $offset = ord(substr($hash, -1)) & 0x0f;
        $segment = substr($hash, $offset, 4);
        $value = unpack('N', $segment)[1] & 0x7fffffff;

        return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
    }

    private function base32Decode(string $input): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $cleanInput = strtoupper(preg_replace('/[^A-Z2-7]/', '', $input) ?? '');
        $bits = '';
        $output = '';

        for ($i = 0; $i < strlen($cleanInput); $i++) {
            $position = strpos($alphabet, $cleanInput[$i]);
            $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
        }

        $chunks = str_split($bits, 8);

        foreach ($chunks as $chunk) {
            if (strlen($chunk) === 8) {
                $output .= chr(bindec($chunk));
            }
        }

        return $output;
    }
}