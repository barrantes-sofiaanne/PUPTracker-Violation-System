<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class StudentIdpLoginController extends Controller
{
    /**
     * Start the SSO flow by redirecting the student to the IDP authorization
     * endpoint. No student number is required — the IDP session is the source
     * of truth, and first-time linking is handled safely in the callback.
     *
     * GET /auth/authorize?client_id=...&redirect_uri=...&response_type=code&state=...
     */
    public function start(Request $request): RedirectResponse
    {
        $clientId = (string) config('services.idp.client_id');
        $baseUrl = rtrim((string) config('services.idp.base_url'), '/');
        $redirectUri = (string) config('services.idp.redirect_uri');

        if ($clientId === '' || $baseUrl === '' || $redirectUri === '') {
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
            'response_type' => 'code',
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
            $refreshToken = (string) $tokenResponse->json('refresh_token');

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

            // IDP role must be student (exact normalized match).
            if (! $this->profileHasStudentRole($profile)) {
                return redirect()->route('student.login')
                    ->withErrors([
                        'login' => 'Your IDP account is not allowed to sign in as a student.',
                    ]);
            }

            $user = $this->resolveLocalUser($idpSubject, $email);

            if (! $user) {
                return redirect()->route('student.login')
                    ->withErrors([
                        'login' => 'Your PUP ID was authenticated, but your PUPTracker student account has not been linked yet. Please contact the administrator.',
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

            // Keep tokens server-side (session) for optional refresh/logout,
            // never exposed to the browser.
            $request->session()->put('student.idp.access_token', $accessToken);
            if ($refreshToken !== '') {
                $request->session()->put('student.idp.refresh_token', $refreshToken);
            }

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
        $rolesRaw = (string) data_get($profile, 'roles', '');

        if (trim($rolesRaw) === '') {
            return true; // No role info returned; rely on local role check.
        }

        $roles = $this->normalizeRoleTokens($rolesRaw);
        $aliases = $this->normalizeRoleTokens((string) config('services.idp.student_roles', 'student'));

        foreach ($roles as $role) {
            if (in_array($role, $aliases, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Split a role string into normalized whole tokens.
     */
    protected function normalizeRoleTokens(string $value): array
    {
        $tokens = preg_split('/[\s,|]+/', strtolower(trim($value)), -1, PREG_SPLIT_NO_EMPTY);

        return $tokens === false ? [] : array_values(array_unique($tokens));
    }

    /**
     * Resolve the local users_tbl record for the given IdP identity.
     *
     * Priority:
     *   1. Existing idp_subject binding (strongest, permanent identity).
     *   2. Safe first-time linking by email: an exact email match to an ACTIVE,
     *      unbound local student. Email is a mutable attribute used ONLY for
     *      controlled initial linking — never the permanent identity — and only
     *      when the target is not already bound to a different IDP subject.
     *
     * Never auto-creates accounts and never links to an ambiguous match.
     */
    protected function resolveLocalUser(string $idpSubject, string $email): ?User
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

        // 2) Safe first-time linking by email (active, unbound, unambiguous).
        if ($email !== '') {
            try {
                $matches = User::query()
                    ->whereRaw('LOWER(email) = ?', [$email])
                    ->get();

                if ($matches->count() === 1) {
                    $candidate = $matches->first();

                    if (
                        $candidate
                        && (int) $candidate->status_id === 1
                        && ($candidate->idp_subject === null || (string) $candidate->idp_subject === '')
                    ) {
                        return $candidate;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Could not query email for safe linking', ['error' => $e->getMessage()]);
            }
        }

        return null;
    }

    /**
     * Persist the IdP subject/email binding on the local user.
     *
     * idp_subject is the permanent identity link; idp_email is a mutable
     * attribute refreshed to the latest IDP email on every login.
     */
    protected function persistIdpBinding(User $user, string $idpSubject, string $email): void
    {
        try {
            $wasFirstLink = $user->idp_subject === null || (string) $user->idp_subject === '';

            $user->forceFill([
                'idp_subject' => $idpSubject,
                'idp_email' => $email !== '' ? $email : $user->idp_email,
                'idp_connected_at' => $wasFirstLink ? now() : ($user->idp_connected_at ?? now()),
            ])->save();
        } catch (\Throwable $e) {
            Log::warning('Could not update idp fields', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Verify the local user is assigned the student role (exact normalized match).
     */
    protected function userIsStudent(User $user): bool
    {
        try {
            $role = $user->role;

            if ($role === null) {
                // No role record; treat "roles_id" as the student role id (2).
                return (int) $user->roles_id === 2;
            }

            $roleName = (string) $role->roles_name;

            return in_array('student', $this->normalizeRoleTokens($roleName), true);
        } catch (\Throwable $e) {
            Log::warning('Could not verify student role', ['error' => $e->getMessage()]);

            return false; // Fail closed when role verification is unavailable.
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