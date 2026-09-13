<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Support\MfaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Administrator sign-in through the PUP Identity Provider (OAuth2
 * authorization-code flow).
 *
 * This is the second of the two administrator login processes:
 *
 *   1. Email + password  → AdminLoginController, then the MFA challenge.
 *   2. PUP IDP           → this controller. The IDP is the identity source and
 *                          its own authentication is the second factor, so no
 *                          MFA challenge is issued.
 *
 * Both paths end at the SAME authenticated placement: an `admins` account on
 * the `admin` guard, so every downstream Admin Module route/authorization is
 * untouched.
 *
 * IDP roles (per the integration spec):
 *   - "Admin"                 → Administrator
 *   - "System Administrator"  → IT Administrator
 *
 * Security posture
 *   - The IDP role decides WHICH local role may sign in, and the local role
 *     has to agree. A mismatch is REJECTED (fail closed) rather than silently
 *     upgrading or downgrading the local account.
 *   - Binding is matched on the IDP-VERIFIED email only. The email is never
 *     taken from user input, so an officer cannot claim someone else's account.
 *   - An account already bound to a different IDP identity can never be
 *     re-bound by a later login.
 */
class AdminIdpLoginController extends Controller
{
    public function __construct(private readonly MfaService $mfaService)
    {
    }

    /**
     * Start the SSO flow: redirect the administrator to the IDP authorization
     * endpoint.
     */
    public function start(Request $request): RedirectResponse
    {
        $clientId = (string) config('services.idp.client_id');
        $baseUrl = rtrim((string) config('services.idp.base_url'), '/');
        $redirectUri = $this->adminRedirectUri();

        if ($clientId === '' || $baseUrl === '' || $redirectUri === '') {
            return redirect()->route('admin.login')
                ->withErrors([
                    'login' => 'IDP login is not configured yet.',
                ]);
        }

        $state = Str::random(40);
        $request->session()->put('admin.idp.state', $state);

        $authorizeUrl = $baseUrl . '/auth/authorize';

        return redirect()->away($authorizeUrl . '?' . http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'state' => $state,
        ]));
    }

    /**
     * Handle the IDP callback: exchange the code, read the profile, verify the
     * IDP role, resolve the local administrator, bind on first login and sign
     * in on the `admin` guard.
     */
    public function callback(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string'],
            'state' => ['nullable', 'string'],
        ]);

        $expectedState = $request->session()->pull('admin.idp.state');

        // Validate state when the IDP echoes it back. The IDP login SPA does not
        // always preserve state, so validation is skipped when it is absent —
        // matching the student flow's behaviour.
        if (
            is_string($expectedState)
            && $expectedState !== ''
            && $request->filled('state')
            && ! hash_equals($expectedState, (string) $request->string('state'))
        ) {
            return $this->fail('The IDP login session expired. Please try again.');
        }

        $clientId = (string) config('services.idp.client_id');
        $clientSecret = (string) config('services.idp.client_secret');
        $baseUrl = rtrim((string) config('services.idp.base_url'), '/');
        $redirectUri = $this->adminRedirectUri();
        $timeout = (int) config('services.idp.timeout', 15);

        if ($clientId === '' || $clientSecret === '' || $baseUrl === '') {
            return $this->fail('IDP login is not configured yet.');
        }

        try {
            // POST /auth/token — body (JSON): { client_id, client_secret, code }
            $tokenResponse = Http::baseUrl($baseUrl)
                ->acceptJson()
                ->asJson()
                ->timeout($timeout)
                ->post('/auth/token', array_filter([
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'code' => $validated['code'],
                    'redirect_uri' => $redirectUri,
                ], fn ($value) => $value !== null && $value !== ''));

            if (! $tokenResponse->successful()) {
                throw new \RuntimeException('Token exchange failed (HTTP ' . $tokenResponse->status() . ').');
            }

            $accessToken = (string) $tokenResponse->json('access_token');

            if ($accessToken === '') {
                throw new \RuntimeException('Missing access token in IDP response.');
            }

            // GET /me — Bearer token → { id, email, first_name, last_name,
            // middle_name, name_suffix, roles }
            $profileResponse = Http::baseUrl($baseUrl)
                ->acceptJson()
                ->withToken($accessToken)
                ->timeout($timeout)
                ->get('/me');

            if (! $profileResponse->successful()) {
                throw new \RuntimeException('Unable to retrieve IDP profile (HTTP ' . $profileResponse->status() . ').');
            }

            $profile = (array) $profileResponse->json();

            $idpSubject = trim((string) data_get($profile, 'id', ''));

            if ($idpSubject === '') {
                return $this->fail('Your IDP account is missing a stable identity reference.');
            }

            $email = strtolower(trim((string) data_get($profile, 'email', '')));

            if ($email === '') {
                return $this->fail('Your IDP account has no verified email address. Please contact the IT Administrator.');
            }

            // The IDP role decides whether an administrator may sign in at all.
            $idpRole = $this->resolveIdpAdminRole($profile);

            if ($idpRole === null) {
                $this->logSecurityEvent('IDP login rejected: account is not an administrator role', [
                    'idp_subject' => $idpSubject,
                    'roles' => (string) data_get($profile, 'roles', ''),
                ]);

                return $this->fail('Your IDP account is not allowed to sign in as an administrator.');
            }

            $admin = $this->resolveLocalAdmin($idpSubject, $email);

            if (! $admin) {
                $this->logSecurityEvent('IDP login rejected: no administrator account for this IDP identity', [
                    'idp_subject' => $idpSubject,
                ]);

                return $this->fail('No administrator account is associated with this PUP IDP account. Please contact the IT Administrator.');
            }

            // The local role must agree with the IDP role. Never upgrade (or
            // downgrade) an account implicitly — that would be privilege
            // escalation driven by the IDP.
            $expectedLocalRole = $idpRole === 'super' ? 'IT Administrator' : 'Admin';

            if (! $this->localRoleMatches($admin, $expectedLocalRole)) {
                $this->logSecurityEvent('IDP login rejected: role mismatch', [
                    'idp_subject' => $idpSubject,
                    'admin_id' => $admin->getKey(),
                    'idp_role' => $idpRole,
                    'expected_local_role' => $expectedLocalRole,
                    'local_role' => (string) $admin->roleName(),
                ]);

                return $this->fail('Your PUPTracker role does not match your PUP IDP role. Please contact the IT Administrator.');
            }

            // Account status mirrors the email/password path.
            $statusId = $admin->adminInfo?->status_id;

            if ($statusId !== null && (int) $statusId !== 1) {
                return $this->fail('Your account has been deactivated. Please contact the IT Administrator.');
            }

            $this->persistIdpBinding($admin, $idpSubject, $email);

            Auth::guard('admin')->login($admin);
            $request->session()->regenerate();

            Log::info('Admin IDP login succeeded', [
                'admin_id' => $admin->getKey(),
                'idp_role' => $idpRole,
                'local_role' => $expectedLocalRole,
            ]);

            return redirect()->route('admin.dashboard');
        } catch (\Throwable $exception) {
            Log::error('Admin IDP login failed.', [
                'error' => $exception->getMessage(),
            ]);

            return $this->fail('Unable to complete IDP login right now. Please try again later.');
        }
    }

    /**
     * Canonical admin redirect URI, falling back to the student one so a single
     * registered IDP client keeps working when the newer setting is absent.
     */
    protected function adminRedirectUri(): string
    {
        $redirectUri = (string) config('services.idp.admin_redirect_uri');

        if ($redirectUri !== '') {
            return $redirectUri;
        }

        return (string) config('services.idp.redirect_uri');
    }

    /**
     * Map the IDP profile's roles onto one of the two administrator roles.
     *
     * Returns 'super' for the System Administrator role, 'admin' for the
     * Administrator role, or null when the profile carries no allowed role.
     * A missing/empty roles value is treated as a denial: unlike the student
     * flow, an administrator login must never fall back to trust.
     */
    protected function resolveIdpAdminRole(array $profile): ?string
    {
        $roles = $this->normalizeRoleTokens((string) data_get($profile, 'roles', ''));

        if ($roles === []) {
            return null;
        }

        $superAliases = $this->normalizeRoleTokens((string) config('services.idp.admin_super_roles', 'System Administrator'));
        $adminAliases = $this->normalizeRoleTokens((string) config('services.idp.admin_roles', 'Admin,System Administrator'));

        // Check the more privileged role first so its aliases always win.
        foreach ($roles as $role) {
            if (in_array($role, $superAliases, true)) {
                return 'super';
            }
        }

        foreach ($roles as $role) {
            if (in_array($role, $adminAliases, true)) {
                return 'admin';
            }
        }

        return null;
    }

    /**
     * Split a role string into normalized whole tokens.
     *
     * IDP roles arrive as a comma-separated string and may contain multi-word
     * roles ("System Administrator"). Matching is done per comma-separated
     * entry so a role can never be matched as a substring of another.
     */
    protected function normalizeRoleTokens(string $value): array
    {
        return collect(explode(',', $value))
            ->map(fn ($token) => strtolower(trim((string) $token)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Resolve the local administrator for an IDP identity.
     *
     * The permanent binding (`idp_subject`) is authoritative. When no account is
     * bound yet, the first login links by the IDP-VERIFIED email — never by
     * user-supplied input.
     */
    protected function resolveLocalAdmin(string $idpSubject, string $email): ?Admin
    {
        try {
            $bound = Admin::query()->where('idp_subject', $idpSubject)->first();

            if ($bound) {
                return $bound;
            }
        } catch (\Throwable $exception) {
            Log::warning('Could not query admins.idp_subject', ['error' => $exception->getMessage()]);

            return null;
        }

        try {
            $byEmail = Admin::query()->whereRaw('LOWER(email) = ?', [$email])->first();

            if (! $byEmail) {
                return null;
            }

            // Never hijack an account already linked to a different IDP identity.
            $existing = (string) ($byEmail->idp_subject ?? '');

            if ($existing !== '' && $existing !== $idpSubject) {
                $this->logSecurityEvent('IDP login rejected: administrator already linked to another IDP identity', [
                    'admin_id' => $byEmail->getKey(),
                    'idp_subject' => $idpSubject,
                ]);

                return null;
            }

            return $byEmail;
        } catch (\Throwable $exception) {
            Log::warning('Could not resolve administrator by email', ['error' => $exception->getMessage()]);

            return null;
        }
    }

    /**
     * Whether the administrator's local role matches the role the IDP assigned.
     */
    protected function localRoleMatches(Admin $admin, string $expectedRole): bool
    {
        $localRole = $admin->roleName();

        if ($localRole === null) {
            // No resolvable local role — fail closed rather than assume.
            return false;
        }

        return strcasecmp(trim($localRole), $expectedRole) === 0;
    }

    /**
     * Persist the IDP binding. `idp_subject` is written once and never moved;
     * `idp_email` is refreshed because the IDP email may change.
     */
    protected function persistIdpBinding(Admin $admin, string $idpSubject, string $email): void
    {
        try {
            if ((string) ($admin->idp_subject ?? '') === '') {
                $admin->idp_connected_at = now();
            }

            $admin->idp_subject = $idpSubject;
            $admin->idp_email = $email;
            $admin->save();
        } catch (\Throwable $exception) {
            Log::warning('Could not persist admin IDP binding', [
                'admin_id' => $admin->getKey(),
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Shared failure path: back to the admin login form with a message.
     */
    protected function fail(string $message): RedirectResponse
    {
        return redirect()->route('admin.login')
            ->withErrors(['login' => $message]);
    }

    protected function logSecurityEvent(string $message, array $context = []): void
    {
        Log::warning('[Admin IDP] ' . $message, $context);
    }
}
