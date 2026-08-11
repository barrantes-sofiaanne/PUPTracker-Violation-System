<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class StudentIdpLoginController extends Controller
{
    /**
     * Pre-link a local student account to the IdP identity.
     *
     * The student enters their local student number first so the IdP subject
     * can be bound to the correct users_tbl record on first login. Once bound,
     * subsequent logins match by idp_subject directly.
     */
    public function start(Request $request)
    {
        $validated = $request->validate([
            'student_number' => ['required', 'string'],
        ]);

        $student = User::query()
            ->where('student_number', $validated['student_number'])
            ->first();

        if (! $student || (int) $student->status_id !== 1) {
            return redirect()->route('student.login')
                ->withErrors([
                    'login' => 'Student account was not found or is inactive.',
                ])
                ->withInput();
        }

        $request->session()->put('student.idp.student_number', (string) $student->student_number);

        return redirect()->route('student.idp.login');
    }

    /**
     * Redirect the user to the IdP authorization endpoint.
     *
     * The IdP Swagger contract (basePath /api/v1):
     *   GET /auth/authorize?client_id=...&redirect_uri=...&state=...
     * The IdP preserves client_id and redirect_uri through its login SPA and
     * returns the authorization code to redirect_uri.
     */
    public function redirect(Request $request)
    {
        $clientId = (string) config('services.idp.client_id');
        $baseUrl = rtrim((string) config('services.idp.base_url'), '/');
        $redirectUri = (string) config('services.idp.redirect_uri', route('student.idp.callback'));

        if ($clientId === '' || $baseUrl === '') {
            return redirect()->route('student.login')
                ->withErrors([
                    'login' => 'IDP login is not configured yet.',
                ]);
        }

        $state = Str::random(40);
        $request->session()->put('student.idp.state', $state);

        $authorizeUrl = $baseUrl . '/auth/authorize';

        return redirect()->away($authorizeUrl . '?' . http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'state' => $state,
        ]));
    }

    /**
     * Handle the IdP callback.
     *
     * Exchanges the authorization code for a token (POST /auth/token, JSON body),
     * fetches the user profile (GET /me, Bearer), maps the IdP identity to a
     * users_tbl record, verifies the local account, and authenticates with the
     * Laravel "student" guard.
     */
    public function callback(Request $request)
    {
        $validated = $request->validate([
            'code' => ['required', 'string'],
            'state' => ['nullable', 'string'],
        ]);

        $expectedState = $request->session()->pull('student.idp.state');

        // Validate state when the IdP echoes it back. The IdP login SPA does not
        // always preserve state, so validation is skipped when state is absent.
        if (
            is_string($expectedState)
            && $expectedState !== ''
            && $request->filled('state')
            && ! hash_equals($expectedState, (string) $request->string('state'))
        ) {
            return redirect()->route('student.login')
                ->withErrors([
                    'login' => 'The IDP login session expired. Please try again.',
                ]);
        }

        $clientId = (string) config('services.idp.client_id');
        $clientSecret = (string) config('services.idp.client_secret');
        $baseUrl = rtrim((string) config('services.idp.base_url'), '/');
        $timeout = (int) config('services.idp.timeout', 15);

        if ($clientId === '' || $clientSecret === '' || $baseUrl === '') {
            return redirect()->route('student.login')
                ->withErrors([
                    'login' => 'IDP login is not configured yet.',
                ]);
        }

        try {
            // POST /auth/token — body (JSON): { client_id, client_secret, code }
            $tokenResponse = Http::baseUrl($baseUrl)
                ->acceptJson()
                ->asJson()
                ->timeout($timeout)
                ->post('/auth/token', [
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'code' => $validated['code'],
                ]);

            if (! $tokenResponse->successful()) {
                throw new \RuntimeException('Token exchange failed (HTTP ' . $tokenResponse->status() . ').');
            }

            $accessToken = (string) $tokenResponse->json('access_token');

            if ($accessToken === '') {
                throw new \RuntimeException('Missing access token in IDP response.');
            }

            // GET /me — Bearer token → UserInfoResponse { id, email, first_name,
            // last_name, middle_name, name_suffix, roles }
            $profileResponse = Http::baseUrl($baseUrl)
                ->acceptJson()
                ->withToken($accessToken)
                ->timeout($timeout)
                ->get('/me');

            if (! $profileResponse->successful()) {
                throw new \RuntimeException('Unable to retrieve IDP profile (HTTP ' . $profileResponse->status() . ').');
            }

            $profile = $profileResponse->json();
            $idpSubject = trim((string) data_get($profile, 'id', ''));
            $email = strtolower(trim((string) data_get($profile, 'email', '')));

            if ($idpSubject === '') {
                return redirect()->route('student.login')
                    ->withErrors([
                        'login' => 'Your IDP account is missing a stable identity reference.',
                    ]);
            }

            if (! $this->profileHasStudentRole($profile)) {
                return redirect()->route('student.login')
                    ->withErrors([
                        'login' => 'Your IDP account is not allowed to sign in as a student.',
                    ]);
            }

            $user = $this->resolveLocalUser($request, $idpSubject, $email);

            if (! $user) {
                return redirect()->route('student.login')
                    ->withErrors([
                        'login' => 'No local student account is linked to this IDP profile. Enter your student number first, then try again.',
                    ]);
            }

            // Security: prevent an IdP account from binding to a local student
            // that is already linked to a different IdP subject.
            if ((string) $user->idp_subject !== '' && (string) $user->idp_subject !== $idpSubject) {
                return redirect()->route('student.login')
                    ->withErrors([
                        'login' => 'This IDP account is not linked to the local student account.',
                    ]);
            }

            if ((int) $user->status_id !== 1) {
                return redirect()->route('student.login')
                    ->withErrors([
                        'login' => 'Your account has been deactivated. Please contact an administrator.',
                    ]);
            }

            if (! $this->userIsStudent($user)) {
                return redirect()->route('student.login')
                    ->withErrors([
                        'login' => 'This account is not assigned the student role.',
                    ]);
            }

            $this->persistIdpBinding($user, $idpSubject, $email);
            $this->touchLastLogin($user);

            Auth::guard('student')->login($user);
            $request->session()->regenerate();

            return redirect()->route('student.dashboard');
        } catch (\Throwable $exception) {
            Log::error('Student IDP login failed.', [
                'error' => $exception->getMessage(),
            ]);

            return redirect()->route('student.login')
                ->withErrors([
                    'login' => 'Unable to complete IDP login right now. Please try again later.',
                ]);
        }
    }

    /**
     * Determine whether the IdP profile carries a student role.
     * IdP /me returns "roles" as a string (e.g. "student").
     */
    protected function profileHasStudentRole(array $profile): bool
    {
        $roles = strtolower((string) data_get($profile, 'roles', ''));
        $aliases = preg_split(
            '/[\s,|]+/',
            strtolower((string) config('services.idp.student_roles', 'student')),
            -1,
            PREG_SPLIT_NO_EMPTY,
        );

        if ($roles === '') {
            return true; // No role info returned; rely on local role check.
        }

        foreach ($aliases as $alias) {
            if (str_contains($roles, $alias)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve the local users_tbl record for the given IdP identity.
     */
    protected function resolveLocalUser(Request $request, string $idpSubject, string $email): ?User
    {
        // 1) Strongest: existing idp_subject binding.
        try {
            $user = User::query()->where('idp_subject', $idpSubject)->first();
            if ($user) {
                return $user;
            }
        } catch (\Throwable $e) {
            Log::warning('Could not query idp_subject column', ['error' => $e->getMessage()]);
        }

        // 2) Pre-linked student number from the start() flow.
        $pendingStudentNumber = (string) $request->session()->pull('student.idp.student_number', '');
        if ($pendingStudentNumber !== '') {
            $user = User::query()->where('student_number', $pendingStudentNumber)->first();
            if ($user) {
                return $user;
            }
        }

        // 3) Legacy fallback for accounts linked before subject-based binding.
        if ($email !== '') {
            return User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        }

        return null;
    }

    /**
     * Persist the IdP subject/email binding on the local user.
     */
    protected function persistIdpBinding(User $user, string $idpSubject, string $email): void
    {
        try {
            if ((string) $user->idp_subject !== $idpSubject || strtolower((string) $user->idp_email) !== $email) {
                $user->forceFill([
                    'idp_subject' => $idpSubject,
                    'idp_email' => $email !== '' ? $email : $user->idp_email,
                    'idp_connected_at' => $user->idp_connected_at ?? now(),
                ])->save();
            }
        } catch (\Throwable $e) {
            Log::warning('Could not update idp fields', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Verify the local user is assigned the student role.
     */
    protected function userIsStudent(User $user): bool
    {
        try {
            $role = $user->role;

            if ($role === null) {
                // No role record; treat "roles_id" as the student role id (2).
                return (int) $user->roles_id === 2;
            }

            $roleName = strtolower((string) $role->roles_name);

            return str_contains($roleName, 'student');
        } catch (\Throwable $e) {
            Log::warning('Could not verify student role', ['error' => $e->getMessage()]);

            return true; // Role verification unavailable; rely on IdP role check.
        }
    }

    /**
     * Update the last login timestamp if the column exists.
     */
    protected function touchLastLogin(User $user): void
    {
        try {
            $user->forceFill([
                'idp_last_login_at' => now(),
            ])->save();
        } catch (\Throwable $e) {
            Log::warning('Could not update idp_last_login_at', ['error' => $e->getMessage()]);
        }
    }
}