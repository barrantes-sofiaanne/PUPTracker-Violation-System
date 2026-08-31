<?php

namespace Tests\Feature;

use App\Mail\PasswordChangedNotification;
use App\Mail\PasswordResetLink;
use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\PasswordReset;
use App\Models\Security;
use App\Models\User;
use App\Services\PasswordResetService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PasswordResetFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureTablesForTests();
        Mail::fake();
    }

    // ------------------------------------------------------------------
    // 1. Forgot password form (email only, no security questions)
    // ------------------------------------------------------------------

    public function test_forgot_password_form_asks_only_for_email(): void
    {
        $response = $this->get(route('password.request', ['guard' => 'admin']));

        $response->assertOk();
        $response->assertSee('Forgot Password');
        $response->assertSee('name="email"', false);
        $response->assertDontSee('Security Question');
        $response->assertDontSee('Birthday');
        $response->assertDontSee('Full Name');
    }

    // ------------------------------------------------------------------
    // 2. Generic response prevents account enumeration
    // ------------------------------------------------------------------

    public function test_sending_reset_link_for_unknown_email_returns_generic_message(): void
    {
        $response = $this->post(route('password.email', ['guard' => 'admin']), [
            'email' => 'does-not-exist@example.com',
        ]);

        $response->assertSessionHas('status', 'If an account is associated with this email address, a password reset link has been sent.');
        $response->assertSessionMissing('errors');
        Mail::assertNothingSent();
    }

    public function test_sending_reset_link_for_known_email_returns_same_generic_message(): void
    {
        Admin::create([
            'username' => 'admin1',
            'email' => 'admin1@example.com',
            'password' => Hash::make('password'),
        ]);

        $response = $this->post(route('password.email', ['guard' => 'admin']), [
            'email' => 'admin1@example.com',
        ]);

        $response->assertSessionHas('status', 'If an account is associated with this email address, a password reset link has been sent.');
    }

    // ------------------------------------------------------------------
    // 3. Token storage — hashed only, bound to account id, expires in 15 min
    // ------------------------------------------------------------------

    public function test_reset_token_is_stored_hashed_and_bound_to_account_id(): void
    {
        $admin = Admin::create([
            'username' => 'admin2',
            'email' => 'admin2@example.com',
            'password' => Hash::make('password'),
        ]);

        $this->post(route('password.email', ['guard' => 'admin']), [
            'email' => 'admin2@example.com',
        ]);

        $record = PasswordReset::where('guard', 'admin')->latest('id')->first();

        $this->assertNotNull($record);
        $this->assertSame((string) $admin->getKey(), (string) $record->account_id);
        $this->assertNotSame($record->token_hash, '');
        $this->assertTrue($record->expires_at->gt(now()));
        $this->assertTrue($record->expires_at->lte(now()->addMinutes(15)));
        $this->assertNull($record->used_at);
    }

    public function test_plaintext_token_is_never_stored_in_database(): void
    {
        $admin = Admin::create([
            'username' => 'admin-token',
            'email' => 'admin-token@example.com',
            'password' => Hash::make('password'),
        ]);

        $this->post(route('password.email', ['guard' => 'admin']), [
            'email' => 'admin-token@example.com',
        ]);

        $sent = Mail::sent(PasswordResetLink::class)->first();
        $this->assertNotNull($sent);
        $plainToken = $this->extractTokenFromUrl($sent->resetUrl);

        $records = PasswordReset::where('guard', 'admin')->get();
        foreach ($records as $record) {
            $this->assertNotSame($plainToken, $record->token_hash);
        }
    }

    // ------------------------------------------------------------------
    // 4. Reset link sent to registered email only
    // ------------------------------------------------------------------

    public function test_reset_link_mail_is_sent_to_registered_email(): void
    {
        Admin::create([
            'username' => 'admin3',
            'email' => 'admin3@example.com',
            'password' => Hash::make('password'),
        ]);

        $this->post(route('password.email', ['guard' => 'admin']), [
            'email' => 'admin3@example.com',
        ]);

        Mail::assertSent(PasswordResetLink::class, function ($mail) {
            return $mail->hasTo('admin3@example.com');
        });
    }

    public function test_reset_link_contains_only_token_not_email(): void
    {
        Admin::create([
            'username' => 'admin4',
            'email' => 'admin4@example.com',
            'password' => Hash::make('password'),
        ]);

        $this->post(route('password.email', ['guard' => 'admin']), [
            'email' => 'admin4@example.com',
        ]);

        Mail::assertSent(PasswordResetLink::class, function ($mail) {
            $url = $mail->resetUrl;
            $this->assertStringContainsString('/password/reset/admin/', $url);
            $this->assertStringNotContainsString('email=', $url);
            $this->assertStringNotContainsString('admin4@example.com', $url);

            return true;
        });
    }

    // ------------------------------------------------------------------
    // 5. Opening the reset link
    // ------------------------------------------------------------------

    public function test_opening_valid_reset_link_shows_password_form_for_student(): void
    {
        User::create([
            'student_number' => '2026-00099',
            'first_name' => 'Reset',
            'last_name' => 'Student',
            'email' => 'reset-student@example.com',
            'status_id' => 1,
            'password_hash' => Hash::make('OldPass123!'),
            'mfa_totp_enabled' => false,
        ]);

        $token = $this->requestResetAndGetToken('student', 'reset-student@example.com');

        $response = $this->get(route('password.reset.form', ['guard' => 'student', 'token' => $token]));

        $response->assertOk();
        $response->assertSee('Reset Password');
        // No email field on the reset form — the token identifies the account.
        $response->assertDontSee('name="email"', false);
    }

    public function test_invalid_token_is_rejected_without_exposing_details(): void
    {
        $response = $this->get(route('password.reset.form', ['guard' => 'admin', 'token' => 'bogus-token']));

        $response->assertRedirect(route('admin.login'));
        $response->assertSessionHasErrors('link');
        $this->assertStringContainsString(
            'Invalid or expired password reset link.',
            session('errors')->first('link')
        );
    }

    public function test_expired_token_is_rejected(): void
    {
        $admin = Admin::create([
            'username' => 'admin5',
            'email' => 'admin5@example.com',
            'password' => Hash::make('password'),
        ]);

        $result = app(PasswordResetService::class)->createReset('admin', $admin);
        $result['record']->update(['expires_at' => now()->subMinute()]);
        $token = $result['token'];

        $response = $this->get(route('password.reset.form', ['guard' => 'admin', 'token' => $token]));

        $response->assertRedirect(route('admin.login'));
        $response->assertSessionHasErrors('link');
    }

    // ------------------------------------------------------------------
    // 6. Privileged accounts require OTP before a new password
    // ------------------------------------------------------------------

    public function test_admin_reset_requires_otp_before_password_form(): void
    {
        $admin = Admin::create([
            'username' => 'admin6',
            'email' => 'admin6@example.com',
            'password' => Hash::make('password'),
        ]);

        $token = $this->requestResetAndGetToken('admin', 'admin6@example.com');

        // Opening the link should show the OTP step, NOT the password form.
        $response = $this->get(route('password.reset.form', ['guard' => 'admin', 'token' => $token]));
        $response->assertOk();
        $response->assertSee('Verification Code');
        $response->assertDontSee('id="newPassword"', false);
        $response->assertSessionHas('password.reset.otp');

        // OTP mail is sent to the account's registered email.
        Mail::assertSent(\App\Mail\OtpCode::class, function ($mail) use ($admin) {
            return $mail->hasTo($admin->email);
        });
    }

    public function test_admin_cannot_reset_password_without_otp(): void
    {
        $admin = Admin::create([
            'username' => 'admin7',
            'email' => 'admin7@example.com',
            'password' => Hash::make('OldPass123!'),
        ]);

        $token = $this->requestResetAndGetToken('admin', 'admin7@example.com');

        $response = $this->post(route('password.update', ['guard' => 'admin']), [
            'token' => $token,
            'password' => 'NewPass1234!',
            'password_confirmation' => 'NewPass1234!',
        ]);

        $response->assertRedirect(route('admin.login'));
        $response->assertSessionHasErrors('link');

        $this->assertFalse(Hash::check('NewPass1234!', $admin->fresh()->password));
    }

    public function test_admin_reset_with_correct_otp_updates_password(): void
    {
        $admin = Admin::create([
            'username' => 'admin8',
            'email' => 'admin8@example.com',
            'password' => Hash::make('OldPass123!'),
        ]);

        $token = $this->requestResetAndGetToken('admin', 'admin8@example.com');

        // Open the link to stage the OTP.
        $this->get(route('password.reset.form', ['guard' => 'admin', 'token' => $token]));
        $staged = session('password.reset.otp');

        $this->assertNotNull($staged);

        // Inject the OTP code into the staged session so we can verify.
        $staged['code_hash'] = Hash::make('123456');
        $this->withSession(['password.reset.otp' => $staged]);

        $otp = $this->post(route('password.reset.otp', ['guard' => 'admin']), [
            'token' => $token,
            'code' => '123456',
        ]);

        $otp->assertOk();
        $otp->assertSee('id="newPassword"', false);

        $reset = $this->post(route('password.update', ['guard' => 'admin']), [
            'token' => $token,
            'password' => 'NewPass1234!',
            'password_confirmation' => 'NewPass1234!',
        ]);

        $reset->assertRedirect(route('admin.login'));
        $reset->assertSessionHas('status');

        $this->assertTrue(Hash::check('NewPass1234!', $admin->fresh()->password));
    }

    // ------------------------------------------------------------------
    // 7. OTP attempt limiting
    // ------------------------------------------------------------------

    public function test_otp_verification_locks_after_max_attempts(): void
    {
        $admin = Admin::create([
            'username' => 'admin9',
            'email' => 'admin9@example.com',
            'password' => Hash::make('OldPass123!'),
        ]);

        $token = $this->requestResetAndGetToken('admin', 'admin9@example.com');
        $this->get(route('password.reset.form', ['guard' => 'admin', 'token' => $token]));

        $response = null;
        for ($i = 0; $i < 6; $i++) {
            $response = $this->post(route('password.reset.otp', ['guard' => 'admin']), [
                'token' => $token,
                'code' => '000000',
            ]);
        }

        $response->assertRedirect(route('admin.login'));
        $response->assertSessionHasErrors('link');
        $this->assertFalse(session()->has('password.reset.otp'));
    }

    // ------------------------------------------------------------------
    // 8. Password policy
    // ------------------------------------------------------------------

    public function test_password_must_meet_minimum_12_character_policy(): void
    {
        $user = User::create([
            'student_number' => '2026-00100',
            'first_name' => 'Policy',
            'last_name' => 'Student',
            'email' => 'policy-student@example.com',
            'status_id' => 1,
            'password_hash' => Hash::make('OldPass123!'),
            'mfa_totp_enabled' => false,
        ]);

        $token = $this->requestResetAndGetToken('student', 'policy-student@example.com');
        $this->get(route('password.reset.form', ['guard' => 'student', 'token' => $token]));

        // Too short (8 chars meets old policy but not new 12-char policy).
        $response = $this->post(route('password.update', ['guard' => 'student']), [
            'token' => $token,
            'password' => 'Short123!',
            'password_confirmation' => 'Short123!',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertFalse(Hash::check('Short123!', $user->fresh()->password_hash));
    }

    // ------------------------------------------------------------------
    // 9. Token invalidation (single use)
    // ------------------------------------------------------------------

    public function test_reset_token_cannot_be_reused_after_password_change(): void
    {
        $user = User::create([
            'student_number' => '2026-00101',
            'first_name' => 'Once',
            'last_name' => 'Use',
            'email' => 'once-use@example.com',
            'status_id' => 1,
            'password_hash' => Hash::make('OldPass123!'),
            'mfa_totp_enabled' => false,
        ]);

        $token = $this->requestResetAndGetToken('student', 'once-use@example.com');
        $this->get(route('password.reset.form', ['guard' => 'student', 'token' => $token]));

        $this->post(route('password.update', ['guard' => 'student']), [
            'token' => $token,
            'password' => 'NewPass1234!',
            'password_confirmation' => 'NewPass1234!',
        ]);

        // First click was valid — second click must be rejected.
        $second = $this->get(route('password.reset.form', ['guard' => 'student', 'token' => $token]));
        $second->assertRedirect(route('student.login'));
        $second->assertSessionHasErrors('link');

        $latest = PasswordReset::where('guard', 'student')->latest('id')->first();
        $this->assertNotNull($latest->used_at);
    }

    // ------------------------------------------------------------------
    // 10. Sessions revoked + audit log + notification
    // ------------------------------------------------------------------

    public function test_password_change_writes_audit_log(): void
    {
        $user = User::create([
            'student_number' => '2026-00102',
            'first_name' => 'Audit',
            'last_name' => 'Student',
            'email' => 'audit-student@example.com',
            'status_id' => 1,
            'password_hash' => Hash::make('OldPass123!'),
            'mfa_totp_enabled' => false,
        ]);

        $token = $this->requestResetAndGetToken('student', 'audit-student@example.com');
        $this->get(route('password.reset.form', ['guard' => 'student', 'token' => $token]));

        $this->post(route('password.update', ['guard' => 'student']), [
            'token' => $token,
            'password' => 'NewPass1234!',
            'password_confirmation' => 'NewPass1234!',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'PASSWORD_RESET_COMPLETED',
            'actor_type' => 'student',
            'actor_id' => $user->getKey(),
        ]);
    }

    public function test_password_change_sends_password_changed_notification(): void
    {
        $user = User::create([
            'student_number' => '2026-00103',
            'first_name' => 'Notify',
            'last_name' => 'Student',
            'email' => 'notify-student@example.com',
            'status_id' => 1,
            'password_hash' => Hash::make('OldPass123!'),
            'mfa_totp_enabled' => false,
        ]);

        $token = $this->requestResetAndGetToken('student', 'notify-student@example.com');
        $this->get(route('password.reset.form', ['guard' => 'student', 'token' => $token]));

        $this->post(route('password.update', ['guard' => 'student']), [
            'token' => $token,
            'password' => 'NewPass1234!',
            'password_confirmation' => 'NewPass1234!',
        ]);

        Mail::assertSent(PasswordChangedNotification::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function requestResetAndGetToken(string $guard, string $email): string
    {
        $this->post(route('password.email', ['guard' => $guard]), [
            'email' => $email,
        ]);

        $record = PasswordReset::where('guard', $guard)->latest('id')->first();
        $this->assertNotNull($record);

        $sent = Mail::sent(PasswordResetLink::class)->first();
        $this->assertNotNull($sent);

        return $this->extractTokenFromUrl($sent->resetUrl);
    }

    private function extractTokenFromUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        $segments = explode('/', rtrim((string) $path, '/'));

        return end($segments);
    }

    private function ensureTablesForTests(): void
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

        if (!Schema::hasTable('password_resets')) {
            Schema::create('password_resets', function (Blueprint $table): void {
                $table->id();
                $table->string('guard', 20);
                $table->unsignedBigInteger('account_id');
                $table->string('token_hash', 255);
                $table->timestamp('expires_at');
                $table->timestamp('used_at')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->index(['guard', 'account_id']);
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
}
