<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Support\MfaService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * After a successful ADMIN MFA challenge, each admin role must land in its OWN
 * module:
 *
 *   Admin             -> admin.dashboard
 *   IT Administrator  -> admin.super-admin.dashboard
 *   OSS Staff         -> oss-staff.dashboard
 *
 * The role checks must FAIL CLOSED: an account whose role cannot be resolved
 * lands on the plain Admin dashboard, never on the OSS Staff or Super Admin
 * module.
 */
class AdminMfaRedirectDestinationTest extends TestCase
{
    private const TOTP_SECRET = 'JBSWY3DPEHPK3PXP';

    protected function setUp(): void
    {
        parent::setUp();

        $this->createTables();
        $this->seedRoles();
    }

    public function test_plain_admin_lands_on_the_admin_dashboard(): void
    {
        $this->assertAdminRoleLandsOn('Admin', 'admin.dashboard');
    }

    public function test_it_administrator_lands_on_the_super_admin_dashboard(): void
    {
        $this->assertAdminRoleLandsOn('IT Administrator', 'admin.super-admin.dashboard');
    }

    public function test_oss_staff_lands_on_the_oss_staff_dashboard(): void
    {
        $this->assertAdminRoleLandsOn('OSS Staff', 'oss-staff.dashboard');
    }

    /** An unresolvable role must NOT be upgraded to any privileged module. */
    public function test_unresolvable_role_fails_closed_to_the_admin_dashboard(): void
    {
        $this->assertAdminRoleLandsOn('Some Fabricated Role', 'admin.dashboard');
    }

    /**
     * Drive a full admin credential login + TOTP verification for an account
     * with the given local role and assert the landing route.
     */
    private function assertAdminRoleLandsOn(string $roleName, string $expectedRoute): void
    {
        $admin = Admin::create([
            'username' => 'mfa-' . md5($roleName),
            'email' => strtolower(str_replace(' ', '-', $roleName)) . '@example.com',
            'password' => Hash::make('adminpass'),
            'mfa_totp_secret' => self::TOTP_SECRET,
            'mfa_totp_enabled' => true,
            // Pre-existing backup codes: the first-MFA backup-code interstitial
            // is a SEPARATE flow (unchanged by this module) and would otherwise
            // intercept the redirect before the role destination is reached.
            'mfa_backup_codes' => json_encode([Hash::make('123456')]),
            'mfa_backup_codes_used' => json_encode([]),
        ]);

        DB::table('admin_info_tbl')->insert([
            'admin_id' => $admin->id,
            'role_id' => DB::table('roles_tbl')->where('roles_name', $roleName)->value('roles_id'),
            'Position' => $roleName,
            'firstname' => 'Test',
            'lastname' => 'Admin',
        ]);

        $this->post(route('admin.login.post'), [
            'email' => $admin->email,
            'password' => 'adminpass',
        ])->assertRedirect(route('mfa.verify.show'));

        $pending = session(MfaService::SESSION_KEY);
        $this->assertIsArray($pending, 'The MFA challenge should have started.');

        $verify = $this->withSession(['mfa.pending' => $pending])
            ->post(route('mfa.verify.submit'), [
                'method' => 'totp',
                'code' => $this->generateCurrentTotp(self::TOTP_SECRET),
            ]);

        $verify->assertRedirect(route($expectedRoute));
        $this->assertAuthenticated('admin');
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

    private function base32Decode(string $secret): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = strtoupper(rtrim($secret, '='));
        $bits = '';

        foreach (str_split($secret) as $char) {
            $index = strpos($alphabet, $char);

            if ($index === false) {
                continue;
            }

            $bits .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
        }

        $binary = '';

        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $binary .= chr(bindec($byte));
            }
        }

        return $binary;
    }

    private function seedRoles(): void
    {
        DB::table('roles_tbl')->insertOrIgnore([
            ['roles_id' => 1, 'roles_name' => 'Admin'],
            ['roles_id' => 2, 'roles_name' => 'IT Administrator'],
            ['roles_id' => 3, 'roles_name' => 'OSS Staff'],
        ]);
    }

    private function createTables(): void
    {
        $tables = [
            'admins' => function (Blueprint $table): void {
                $table->increments('id');
                $table->string('username')->nullable();
                $table->string('email')->unique();
                $table->string('password');
                $table->string('remember_token', 100)->nullable();
                $table->text('mfa_totp_secret')->nullable();
                $table->boolean('mfa_totp_enabled')->default(false);
                $table->text('mfa_backup_codes')->nullable();
                $table->text('mfa_backup_codes_used')->nullable();
            },
            'admin_info_tbl' => function (Blueprint $table): void {
                $table->unsignedBigInteger('admin_id')->primary();
                $table->unsignedBigInteger('role_id')->nullable();
                $table->string('firstname')->nullable();
                $table->string('middlename')->nullable();
                $table->string('lastname')->nullable();
                $table->string('Position')->nullable();
                $table->string('designation')->nullable();
                $table->string('role')->nullable();
                $table->string('title')->nullable();
            },
            'roles_tbl' => function (Blueprint $table): void {
                $table->increments('roles_id');
                $table->string('roles_name');
            },
            'security' => function (Blueprint $table): void {
                $table->increments('id');
                $table->string('email')->unique();
                $table->string('password');
                $table->string('remember_token', 100)->nullable();
                $table->text('mfa_totp_secret')->nullable();
                $table->boolean('mfa_totp_enabled')->default(false);
            },
            'users_tbl' => function (Blueprint $table): void {
                $table->increments('user_id');
                $table->string('student_number')->nullable();
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('email')->nullable();
                $table->string('password_hash')->nullable();
                $table->unsignedInteger('roles_id')->nullable();
                $table->unsignedInteger('status_id')->nullable();
            },
            'audit_logs' => function (Blueprint $table): void {
                $table->increments('audit_log_id');
                $table->string('actor_type')->nullable();
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->string('action');
                $table->string('module')->nullable();
                $table->text('description')->nullable();
                $table->timestamp('created_at')->nullable();
            },
        ];

        foreach ($tables as $name => $definition) {
            if (! Schema::hasTable($name)) {
                Schema::create($name, $definition);
            }
        }
    }
}
