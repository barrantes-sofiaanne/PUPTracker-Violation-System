<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The Administrator module has TWO login processes:
 *
 *   1. Email + password  → AdminLoginController (then the MFA challenge)
 *   2. PUP IDP           → AdminIdpLoginController (the IDP is the second factor)
 *
 * Both must end at the same authenticated placement: an `admins` account on the
 * `admin` guard. The IDP role decides which local role may sign in:
 *
 *   IDP "Admin"                → local "Admin"              (Administrator)
 *   IDP "System Administrator" → local "IT Administrator"   (IT Administrator)
 */
class AdminIdpLoginTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createTables();

        Config::set('services.idp.base_url', 'https://idp.test/api/v1');
        Config::set('services.idp.client_id', 'test-client');
        Config::set('services.idp.client_secret', 'test-secret');
        Config::set('services.idp.redirect_uri', 'https://app.test/student/idp/callback');
        Config::set('services.idp.admin_redirect_uri', 'https://app.test/admin/idp/callback');
        Config::set('services.idp.admin_roles', 'Admin,System Administrator');
        Config::set('services.idp.admin_super_roles', 'System Administrator');
    }

    // ==================================================================
    // 1. IDP role → local role mapping
    // ==================================================================

    public function test_idp_admin_role_signs_in_the_local_administrator(): void
    {
        $admin = $this->createAdmin('admin@example.com', 'Admin');

        $this->fakeIdp('idp-admin-1', 'admin@example.com', 'Admin');

        $this->hitCallback()->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin, 'admin');
        $this->assertSame('idp-admin-1', $admin->fresh()->idp_subject);
    }

    public function test_idp_system_administrator_role_signs_in_the_it_administrator(): void
    {
        $admin = $this->createAdmin('it@example.com', 'IT Administrator');

        $this->fakeIdp('idp-it-1', 'it@example.com', 'System Administrator');

        $this->hitCallback()->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin, 'admin');
        $this->assertSame('idp-it-1', $admin->fresh()->idp_subject);
    }

    // ==================================================================
    // 2. Role mismatch must fail closed
    // ==================================================================

    /**
     * A regular Administrator IDP role must NOT be able to sign in to an IT
     * Administrator account: that would be privilege escalation.
     */
    public function test_idp_admin_role_cannot_sign_in_an_it_administrator_account(): void
    {
        $admin = $this->createAdmin('it@example.com', 'IT Administrator');

        $this->fakeIdp('idp-admin-2', 'it@example.com', 'Admin');

        $this->hitCallback()
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors('login');

        $this->assertGuest('admin');
        $this->assertSame(null, $admin->fresh()->idp_subject, 'A rejected login must not bind the IDP identity.');
    }

    /**
     * The IT Administrator IDP role must NOT silently downgrade into a regular
     * Administrator account either — the mismatch is rejected in both directions.
     */
    public function test_idp_system_administrator_role_cannot_sign_in_a_regular_admin_account(): void
    {
        $admin = $this->createAdmin('admin@example.com', 'Admin');

        $this->fakeIdp('idp-it-2', 'admin@example.com', 'System Administrator');

        $this->hitCallback()
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors('login');

        $this->assertGuest('admin');
        $this->assertSame(null, $admin->fresh()->idp_subject);
    }

    public function test_non_administrator_idp_role_is_rejected(): void
    {
        $admin = $this->createAdmin('admin@example.com', 'Admin');

        $this->fakeIdp('idp-student', 'admin@example.com', 'student');

        $this->hitCallback()
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors('login');

        $this->assertGuest('admin');
    }

    /**
     * Unlike the student flow, a missing role must NOT be trusted: an admin
     * login fails closed when the IDP returns no role information.
     */
    public function test_missing_idp_role_is_rejected_for_administrators(): void
    {
        $this->createAdmin('admin@example.com', 'Admin');

        $this->fakeIdp('idp-norole', 'admin@example.com', '');

        $this->hitCallback()
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors('login');

        $this->assertGuest('admin');
    }

    /**
     * Role matching is whole-token, so a longer role containing an allowed name
     * must never be treated as that role.
     */
    public function test_role_matching_is_exact_and_not_substring(): void
    {
        $this->createAdmin('admin@example.com', 'Admin');

        $this->fakeIdp('idp-sneaky', 'admin@example.com', 'System Administrator Assistant');

        $this->hitCallback()
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors('login');

        $this->assertGuest('admin');
    }

    // ==================================================================
    // 3. Account resolution / linking
    // ==================================================================

    public function test_unknown_email_is_rejected_and_does_not_authenticate(): void
    {
        $this->fakeIdp('idp-ghost', 'nobody@example.com', 'Admin');

        $this->hitCallback()
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors('login');

        $this->assertGuest('admin');
    }

    public function test_existing_binding_logs_in_and_refreshes_the_idp_email(): void
    {
        $admin = $this->createAdmin('admin@example.com', 'Admin');
        $admin->idp_subject = 'idp-bound';
        $admin->idp_email = 'old@example.com';
        $admin->save();

        $this->fakeIdp('idp-bound', 'admin@example.com', 'Admin');

        $this->hitCallback()->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin, 'admin');
        $this->assertSame('admin@example.com', $admin->fresh()->idp_email);
    }

    /**
     * An account already bound to another IDP identity can never be re-bound,
     * even if the email matches.
     */
    public function test_account_bound_to_a_different_idp_identity_is_rejected(): void
    {
        $admin = $this->createAdmin('admin@example.com', 'Admin');
        $admin->idp_subject = 'idp-original';
        $admin->save();

        $this->fakeIdp('idp-different', 'admin@example.com', 'Admin');

        $this->hitCallback()
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors('login');

        $this->assertGuest('admin');
        $this->assertSame('idp-original', $admin->fresh()->idp_subject);
    }

    public function test_deactivated_administrator_cannot_sign_in_through_idp(): void
    {
        $admin = $this->createAdmin('admin@example.com', 'Admin', statusId: 2);

        $this->fakeIdp('idp-inactive', 'admin@example.com', 'Admin');

        $this->hitCallback()
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors('login');

        $this->assertGuest('admin');
    }

    /**
     * An admin with no resolvable local role is denied (fail closed).
     */
    public function test_administrator_without_a_local_role_is_denied(): void
    {
        $admin = Admin::create([
            'username' => 'norole@example.com',
            'email' => 'norole@example.com',
            'password' => 'secret',
        ]);

        DB::table('admin_info_tbl')->insert([
            'admin_id' => $admin->id,
            'status_id' => 1,
            'role_id' => null,
        ]);

        $this->fakeIdp('idp-norole2', 'norole@example.com', 'Admin');

        $this->hitCallback()
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors('login');

        $this->assertGuest('admin');
    }

    // ==================================================================
    // 4. Leftover MFA enrolment must not block the IDP login
    // ==================================================================

    /**
     * An administrator who never finished MFA enrolment can still reach the IDP
     * login. These endpoints are deliberately NOT behind `mfa.enforced`; if they
     * were, a first-time admin would be locked out of both login methods.
     */
    public function test_idp_login_route_is_reachable_while_mfa_enforcement_is_pending(): void
    {
        $this->get(route('admin.idp.login'))
            ->assertRedirectContains('idp.test');
    }

    // ==================================================================
    // 5. Start / redirect behaviour
    // ==================================================================

    public function test_start_redirects_to_the_idp_authorize_endpoint(): void
    {
        $response = $this->get(route('admin.idp.login'));

        $response->assertRedirectContains('https://idp.test/api/v1/auth/authorize');

        // The admin-specific redirect URI is used when configured.
        $this->assertStringContainsString(
            urlencode('https://app.test/admin/idp/callback'),
            (string) $response->headers->get('location')
        );
    }

    public function test_start_falls_back_to_the_shared_redirect_uri_when_unset(): void
    {
        Config::set('services.idp.admin_redirect_uri', null);

        $response = $this->get(route('admin.idp.login'));

        $this->assertStringContainsString(
            urlencode('https://app.test/student/idp/callback'),
            (string) $response->headers->get('location')
        );
    }

    public function test_start_reports_when_idp_is_not_configured(): void
    {
        Config::set('services.idp.client_id', '');

        $this->get(route('admin.idp.login'))
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors('login');
    }

    public function test_mismatched_state_is_rejected(): void
    {
        $this->createAdmin('admin@example.com', 'Admin');

        $this->fakeIdp('idp-state', 'admin@example.com', 'Admin');

        $response = $this->withSession(['admin.idp.state' => 'expected-state'])
            ->get(route('admin.idp.callback', [
                'code' => 'auth-code',
                'state' => 'different-state',
            ]));

        $response->assertRedirect(route('admin.login'))->assertSessionHasErrors('login');
        $this->assertGuest('admin');
    }

    // ==================================================================
    // 6. Both login processes still work
    // ==================================================================

    public function test_email_password_login_still_starts_the_mfa_challenge(): void
    {
        $admin = Admin::create([
            'username' => 'admin@example.com',
            'email' => 'admin@example.com',
            'password' => bcrypt('correct-password'),
            'mfa_totp_enabled' => 0,
        ]);

        DB::table('admin_info_tbl')->insert([
            'admin_id' => $admin->id,
            'status_id' => 1,
            'role_id' => $this->roleId('Admin'),
        ]);

        $this->post(route('admin.login.post'), [
            'email' => 'admin@example.com',
            'password' => 'correct-password',
        ])->assertRedirect(route('mfa.verify.show'));

        // The emailed/password path must NOT authenticate before MFA passes.
        $this->assertGuest('admin');
    }

    public function test_email_password_login_rejects_bad_credentials(): void
    {
        Admin::create([
            'username' => 'admin@example.com',
            'email' => 'admin@example.com',
            'password' => bcrypt('correct-password'),
            'mfa_totp_enabled' => 0,
        ]);

        $this->post(route('admin.login.post'), [
            'email' => 'admin@example.com',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('login');

        $this->assertGuest('admin');
    }

    public function test_admin_login_page_offers_both_login_options(): void
    {
        $html = $this->get(route('admin.login'))->assertOk()->getContent();

        // Email + password form still present.
        $this->assertStringContainsString('name="email"', $html);
        $this->assertStringContainsString('name="password"', $html);

        // And the IDP option.
        $this->assertStringContainsString(route('admin.idp.login'), $html);
        $this->assertStringContainsString('Login through PUP IDP', $html);

        // The redirect state renders: an overlay with a manual fallback link.
        // The script is pushed from inside a section, so this also guards that
        // the push actually reaches the layout's script stack.
        $this->assertStringContainsString('id="adminIdpOverlay"', $html);
        $this->assertStringContainsString('adminIdpLoginButton', $html);
        $this->assertStringContainsString('Redirecting to PUP IDP', $html);
        $this->assertStringContainsString('closest(\'#adminIdpLoginButton\')', $html);
    }

    // ==================================================================
    // Helpers
    // ==================================================================

    private function fakeIdp(string $subject, string $email, string $roles): void
    {
        Http::fake([
            '*/auth/token' => Http::response(['access_token' => 'token-abc']),
            '*/me' => Http::response([
                'id' => $subject,
                'email' => $email,
                'first_name' => 'Test',
                'last_name' => 'Admin',
                'roles' => $roles,
            ]),
        ]);
    }

    private function hitCallback()
    {
        return $this->get(route('admin.idp.callback', ['code' => 'auth-code']));
    }

    private function createAdmin(string $email, string $roleName, int $statusId = 1): Admin
    {
        $admin = Admin::create([
            'username' => $email,
            'email' => $email,
            'password' => 'secret',
            'mfa_totp_enabled' => 0,
        ]);

        DB::table('admin_info_tbl')->insert([
            'admin_id' => $admin->id,
            'status_id' => $statusId,
            'role_id' => $this->roleId($roleName),
        ]);

        return $admin;
    }

    private function roleId(string $roleName): int
    {
        return (int) DB::table('roles_tbl')->where('roles_name', $roleName)->value('roles_id');
    }

    private function createTables(): void
    {
        if (! Schema::hasTable('roles_tbl')) {
            Schema::create('roles_tbl', function (Blueprint $table): void {
                $table->increments('roles_id');
                $table->string('roles_name')->nullable();
            });

            DB::table('roles_tbl')->insert([
                ['roles_id' => 1, 'roles_name' => 'Admin'],
                ['roles_id' => 2, 'roles_name' => 'Student'],
                ['roles_id' => 3, 'roles_name' => 'Security'],
                ['roles_id' => 4, 'roles_name' => 'IT Administrator'],
            ]);
        }

        if (! Schema::hasTable('status_tbl')) {
            Schema::create('status_tbl', function (Blueprint $table): void {
                $table->increments('status_id');
                $table->string('status_name')->nullable();
            });
        }

        if (! Schema::hasTable('admins')) {
            Schema::create('admins', function (Blueprint $table): void {
                $table->increments('id');
                $table->string('username')->nullable();
                $table->string('email')->unique();
                $table->string('password');
                $table->string('remember_token', 100)->nullable();
                $table->boolean('mfa_totp_enabled')->default(false);
                $table->text('mfa_totp_secret')->nullable();
                $table->text('mfa_backup_codes')->nullable();
                $table->text('mfa_backup_codes_used')->nullable();
                $table->timestamp('reset_token_expires_at')->nullable();
                // IDP linkage (mirrors users_tbl).
                $table->string('idp_subject')->nullable()->unique();
                $table->string('idp_email')->nullable();
                $table->timestamp('idp_connected_at')->nullable();
            });
        }

        if (! Schema::hasTable('admin_info_tbl')) {
            Schema::create('admin_info_tbl', function (Blueprint $table): void {
                $table->unsignedBigInteger('admin_id')->primary();
                $table->string('firstname')->nullable();
                $table->string('middlename')->nullable();
                $table->string('lastname')->nullable();
                $table->string('position')->nullable();
                $table->string('designation')->nullable();
                $table->string('role')->nullable();
                $table->string('title')->nullable();
                $table->unsignedBigInteger('status_id')->nullable();
                $table->unsignedBigInteger('role_id')->nullable();
            });
        }

        if (! Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $table): void {
                $table->increments('audit_log_id');
                $table->string('actor_type')->nullable();
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->string('action');
                $table->string('module')->nullable();
                $table->text('description')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }
    }
}
