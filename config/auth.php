<?php

use App\Models\User;

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | This option defines the default authentication "guard" and password
    | reset "broker" for your application. You may change these values
    | as required, but they're a perfect start for most applications.
    |
    */

'defaults' => [
    'guard' => 'student',
    'passwords' => 'students',
],

    /*
    |--------------------------------------------------------------------------
    | Feature switches — auth/security controls
    |--------------------------------------------------------------------------
    |
    | These two controls are DISABLED for now.
    |
    | The implementation is left fully intact underneath so the features can be
    | re-enabled by flipping a single flag — no code has to be restored. Both
    | default to false, and the env variables let a deployment turn them back
    | on without a code change.
    |
    |  remember_device
    |      The "Remember this device for 12 hours" checkbox on the MFA screen.
    |      While disabled the checkbox is not rendered AND the backend refuses to
    |      honour a remember request, so a crafted POST cannot create a
    |      persistent credential. The recaller cookie lifetime
    |      (auth.guards.*.remember) is left configured for when it is re-enabled.
    |
    |  inactivity_timeout
    |      The 15-minute inactivity expiry (EnforceInactivityTimeout). While
    |      disabled the middleware passes every request straight through: no
    |      activity stamp is written, no session is invalidated for idleness, and
    |      no "session expired" notice is produced. Ordinary session.lifetime
    |      still applies, so sessions do not live forever.
    |
    */

'features' => [
    'remember_device' => (bool) env('AUTH_REMEMBER_DEVICE_ENABLED', false),
    'inactivity_timeout' => (bool) env('AUTH_INACTIVITY_TIMEOUT_ENABLED', true),
],

'inactivity_timeout_minutes' => (int) env(
    'AUTH_INACTIVITY_TIMEOUT_MINUTES',
    15
),

'inactivity_warning_seconds' => (int) env(
    'AUTH_INACTIVITY_WARNING_SECONDS',
    10
),

'guards' => [

    'student' => [
        'driver' => 'session',
        'provider' => 'students',
    ],

    'admin' => [
        'driver' => 'session',
        'provider' => 'admins',
        // "Remember me" cookie lifetime for the Administrator module: 12 HOURS.
        //
        // UNIT: MINUTES. AuthManager::createSessionDriver() passes this to
        // SessionGuard::setRememberDuration(), whose own docblock reads "the
        // number of minutes that the remember me cookie should be valid for",
        // and the cookie jar then multiplies it by 60.
        //
        // 12 hours = 720 minutes. The value here used to be 43200, which is
        // 43200 MINUTES = 30 DAYS — a remembered device survived a month
        // instead of half a day. After this window the recaller cookie expires
        // and the user must log in again (password + MFA).
        'remember' => 720,
    ],

    'security' => [
        'driver' => 'session',
        'provider' => 'security',
        // "Remember me" cookie lifetime for the Security module: 12 HOURS.
        // UNIT: MINUTES — see the administrator guard above. 12 hours = 720.
        'remember' => 720,
    ],
],

    /*
    |--------------------------------------------------------------------------
    | Login Attempt Lockout
    |--------------------------------------------------------------------------
    |
    | The maximum number of FAILED credential attempts allowed per module before
    | that module's login is temporarily blocked, and how long the block lasts.
    |
    | Enforced by App\Support\Auth\LoginAttemptLimiter, which is shared by the
    | Administrator and Security login controllers so the rule cannot drift
    | between them. The limiter keys on the SUBMITTED email plus the client IP,
    | so a failure against one module never blocks another, and rotating IPs
    | cannot buy extra guesses against a known account.
    |
    | Counters live in the application cache (server-side), so refreshing the
    | login page cannot reset them, and they are unaffected by session lifetime
    | and by the 15-minute inactivity timeout.
    |
    */

    'login_attempts' => [

        // Applied to any module without its own entry below.
        'default' => [
            'max' => (int) env('LOGIN_MAX_ATTEMPTS', 3),
            'lockout_seconds' => (int) env('LOGIN_LOCKOUT_SECONDS', 900),
        ],

        // Per-module overrides. Add a key named after the module to give that
        // module its own budget without touching the others.
        'admin' => [
            'max' => (int) env('ADMIN_LOGIN_MAX_ATTEMPTS', env('LOGIN_MAX_ATTEMPTS', 3)),
            'lockout_seconds' => (int) env('ADMIN_LOGIN_LOCKOUT_SECONDS', env('LOGIN_LOCKOUT_SECONDS', 900)),
        ],

        'security' => [
            'max' => (int) env('SECURITY_LOGIN_MAX_ATTEMPTS', env('LOGIN_MAX_ATTEMPTS', 3)),
            'lockout_seconds' => (int) env('SECURITY_LOGIN_LOCKOUT_SECONDS', env('LOGIN_LOCKOUT_SECONDS', 900)),
        ],

    ],
    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | Next, you may define every authentication guard for your application.
    | Of course, a great default configuration has been defined for you
    | which utilizes session storage plus the Eloquent user provider.
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | Supported: "session"
    |
    */

    'providers' => [

    'students' => [
        'driver' => 'eloquent',
        'model' => App\Models\User::class,
    ],

    'admins' => [
        'driver' => 'eloquent',
        'model' => App\Models\Admin::class,
    ],

    'security' => [
        'driver' => 'eloquent',
        'model' => App\Models\Security::class,
    ],

],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | If you have multiple user tables or models you may configure multiple
    | providers to represent the model / table. These providers may then
    | be assigned to any extra authentication guards you have defined.
    |
    | Supported: "database", "eloquent"
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    |
    | These configuration options specify the behavior of Laravel's password
    | reset functionality, including the table utilized for token storage
    | and the user provider that is invoked to actually retrieve users.
    |
    | The expiry time is the number of minutes that each reset token will be
    | considered valid. This security feature keeps tokens short-lived so
    | they have less time to be guessed. You may change this as needed.
    |
    | The throttle setting is the number of seconds a user must wait before
    | generating more password reset tokens. This prevents the user from
    | quickly generating a very large amount of password reset tokens.
    |
    */

  'passwords' => [

    'students' => [
        'provider' => 'students',
        'table' => 'password_reset_tokens',
        'expire' => 60,
        'throttle' => 60,
    ],

    'admins' => [
        'provider' => 'admins',
        'table' => 'password_reset_tokens',
        'expire' => 60,
        'throttle' => 60,
    ],

    'security' => [
        'provider' => 'security',
        'table' => 'password_reset_tokens',
        'expire' => 60,
        'throttle' => 60,
    ],

],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    |
    | Here you may define the number of seconds before a password confirmation
    | window expires and users are asked to re-enter their password via the
    | confirmation screen. By default, the timeout lasts for three hours.
    |
    */

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
