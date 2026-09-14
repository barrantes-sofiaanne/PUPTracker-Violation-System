<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Admin;
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
 *   1. Email + password  → AdminLoginController, then the PUPTracker MFA
 *                          challenge (unchanged).
 *   2. PUP IDP           → this controller. A successful IDP login completes
 *                          the sign-in directly; no separate PUPTracker MFA
 *                          challenge is raised on this path.
 *
 * Both paths end at the SAME authenticated placement: an `admins` account on
 * the `admin` guard, so every downstream Admin Module route/authorization is
 * untouched.
 *
 * IDP role requirement:
 *   The PUP IDP reliably exposes only the "Admin" role. It does NOT expose a
 *   trustworthy System Administrator / Super Administrator distinction via the
 *   authenticated /me response, so this flow authorises on the IDP "Admin"
 *   role alone (config `services.idp.admin_roles`).
 *
 * Security posture
 *   - The IDP role is an ENTRY requirement only. It never decides which local
 *     role the account holds, so a successful IDP login can never grant (or
 *     revoke) IT Administrator / Super Admin privileges. Those continue to come
 *     exclusively from the local admin record (`Admin::isItAdministrator()`).
 *   - The IDP is the sole authentication factor on this path. That is a
 *     deliberate, explicit trade-off: the IDP is trusted to have authenticated
 *     the user (including any MFA it enforces), and PUPTracker does not add a
 *     second challenge. The email/password path keeps MFA.
 *   - Binding is matched on the IDP-VERIFIED email only. The email is never
 *     taken from user input, so an officer cannot claim someone else's account.
 *   - An account already bound to a different IDP identity can never be
 *     re-bound by a later login.
 */
class AdminIdpLoginController extends Controller
{
    /**
     * Where a successfully authenticated administrator lands.
     *
     * Derived from the LOCAL admin record only — never from the IDP role — so
     * the IDP can neither grant nor remove Super Admin access.
     */
    protected function destinationRouteFor(Admin $admin): string
    {
        return $admin->isItAdministrator()
            ? 'admin.super-admin.dashboard'
            : 'admin.dashboard';
    }

    /**
     * Start the SSO flow: redirect the administrator to the IDP authorization
     * endpoint.
     */
    public function start(Request $request): RedirectResponse
    {
        // Dedicated administrator OAuth client. NEVER the student client: the
        // two are separate registered IDP app clients, and crossing them sends
        // an administrator to a callback that rejects non-student roles.
        $clientId = $this->adminClientId();
        $baseUrl = rtrim((string) config('services.idp.base_url'), '/');
        $redirectUri = $this->adminRedirectUri();

        if ($clientId === '' || $baseUrl === '' || $redirectUri === '') {
            Log::error('Admin IDP login is not configured.', [
                'has_admin_client_id' => $clientId !== '',
                'has_base_url' => $baseUrl !== '',
                'has_admin_redirect_uri' => $redirectUri !== '',
                'hint' => 'Set IDP_ADMIN_CLIENT_ID and IDP_ADMIN_REDIRECT_URI (e.g. https://<host>/admin/idp/callback). Neither is taken from the IDP_CLIENT_ID / IDP_REDIRECT_URI student settings.',
            ]);

            return redirect()->route('admin.login')
                ->withErrors([
                    'login' => 'Admin IDP login is not configured yet.',
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

        $clientId = $this->adminClientId();
        $clientSecret = $this->adminClientSecret();
        $baseUrl = rtrim((string) config('services.idp.base_url'), '/');
        $redirectUri = $this->adminRedirectUri();
        $timeout = (int) config('services.idp.admin_timeout', config('services.idp.timeout', 15));

        if ($clientId === '' || $clientSecret === '' || $baseUrl === '') {
            Log::error('Admin IDP callback is not configured.', [
                'has_admin_client_id' => $clientId !== '',
                'has_admin_client_secret' => $clientSecret !== '',
                'has_base_url' => $baseUrl !== '',
                'hint' => 'Set IDP_ADMIN_CLIENT_ID and IDP_ADMIN_CLIENT_SECRET. They are never taken from the student client.',
            ]);

            return $this->fail('Admin IDP login is not configured yet.');
        }

        // A callback that is missing its own redirect URI must never silently
        // continue: without it we cannot compare the exchange against the URI the
        // authorization request actually used.
        if ($redirectUri === '') {
            Log::error('Admin IDP callback reached without IDP_ADMIN_REDIRECT_URI configured.', [
                'hint' => 'Set IDP_ADMIN_REDIRECT_URI to https://<host>/admin/idp/callback.',
            ]);

            return $this->fail('Admin IDP login is not configured yet.');
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

            // The IDP role authorises ENTRY only. Which local role the account
            // holds is never derived from it: a matching local account is all
            // that is required, and IT Administrator / Super Admin privileges
            // continue to come from the local record alone. This is what stops
            // an IDP role from escalating privilege.
            //
            // Account status mirrors the email/password path.
            $statusId = $admin->adminInfo?->status_id;

            if ($statusId !== null && (int) $statusId !== 1) {
                return $this->fail('Your account has been deactivated. Please contact the IT Administrator.');
            }

            $this->persistIdpBinding($admin, $idpSubject, $email);

            // The PUP IDP is the sole authentication authority here, so a
            // successful IDP login completes the sign-in directly. No separate
            // PUPTracker MFA challenge is raised on this path — the IDP's own
            // authentication (including any MFA it enforces) is the factor.
            //
            // The email/password path in AdminLoginController is unchanged and
            // still requires MFA.
            // Keep the token server-side (session) only — never exposed to the
            // browser — so logout can revoke the IDP SSO session as well as the
            // local one. This is what makes "logout here = logout at the IDP".
            $request->session()->put('admin.idp.access_token', $accessToken);

            $refreshToken = (string) $tokenResponse->json('refresh_token');
            if ($refreshToken !== '') {
                $request->session()->put('admin.idp.refresh_token', $refreshToken);
            }

            Auth::guard('admin')->login($admin);
            $request->session()->regenerate();
            $request->session()->put('show_privacy_consent_modal', true);

            Log::info('Admin IDP login succeeded', [
                'admin_id' => $admin->getKey(),
                'idp_role' => $idpRole,
            ]);

            // Destination is chosen from the LOCAL role, never from the IDP role:
            // an IT Administrator goes to the Super Admin dashboard, every other
            // administrator to the standard Admin dashboard.
            return redirect()->route($this->destinationRouteFor($admin));
        } catch (\Throwable $exception) {
            Log::error('Admin IDP login failed.', [
                'error' => $exception->getMessage(),
            ]);

            return $this->fail('Unable to complete IDP login right now. Please try again later.');
        }
    }

    /**
     * The dedicated administrator OAuth client id.
     *
     * Deliberately has no fallback to the shared/student client: reusing the
     * student client with the administrator callback is precisely the
     * misconfiguration that sent a System Administrator to /student/login.
     */
    protected function adminClientId(): string
    {
        return trim((string) config('services.idp.admin_client_id'));
    }

    /**
     * The dedicated administrator OAuth client secret (server-side only).
     *
     * Never logged, rendered, or exposed; read from config so a cached config
     * cannot leak it into a view. No fallback to the student secret.
     */
    protected function adminClientSecret(): string
    {
        return trim((string) config('services.idp.admin_client_secret'));
    }

    /**
     * The administrator IDP callback URI.
     *
     * This MUST be configured explicitly and is deliberately never derived from
     * the student redirect URI. The two callbacks have different contracts: the
     * student endpoint rejects any IDP role that is not a student, so pointing an
     * administrator at it authenticates them successfully at the IDP and then
     * bounces them to /student/login with "not allowed to sign in as a student".
     *
     * An empty return therefore means "not configured", and callers must fail
     * closed on it.
     */
    protected function adminRedirectUri(): string
    {
        return trim((string) config('services.idp.admin_redirect_uri'));
    }

    /**
     * Map the IDP profile's roles onto the administrator entry requirement.
     *
     * Returns 'super' when the profile carries the System Administrator role or
     * 'admin' for the Administrator role, and null when no allowed role is
     * present. The distinction is informational only (it is logged); PUPTracker
     * privileges are never derived from it.
     *
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
