<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class MfaService
{
    private const SESSION_KEY = 'mfa.pending';
    private const REMEMBER_COOKIE_KEY = 'mfa_trusted';
    private const LEGACY_REMEMBER_COOKIE_KEY = 'mfa.trusted';

    public function beginChallenge(
        Request $request,
        string $guard,
        string $identifier,
        string $email,
        ?string $totpSecret = null,
        bool $totpEnabled = false
    ): void
    {
        $code = $this->generateCode();
        $hasTotp = $totpEnabled && !empty($totpSecret);
        $methods = $hasTotp ? ['email', 'totp'] : ['email'];

        $request->session()->put(self::SESSION_KEY, [
            'guard' => $guard,
            'identifier' => $identifier,
            'email' => $email,
            'email_masked' => $this->maskEmail($email),
            'totp_secret' => $hasTotp ? $totpSecret : null,
            'methods' => $methods,
            'selected_method' => 'email',
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(5)->timestamp,
            'resend_available_at' => now()->addSeconds(30)->timestamp,
            'attempts' => 0,
        ]);

        $this->sendCode($email, $code);

        $this->audit(
            $guard,
            $identifier,
            'mfa.challenge.started',
            'Authentication',
            'MFA challenge started using methods: ' . implode(', ', $methods)
        );
    }

    public function hasPending(Request $request): bool
    {
        return $request->session()->has(self::SESSION_KEY);
    }

    public function getPending(Request $request): ?array
    {
        return $request->session()->get(self::SESSION_KEY);
    }

    public function clear(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }

    public function verifyCode(Request $request, string $code, string $method = 'email'): array
    {
        $pending = $this->getPending($request);

        if (!$pending) {
            $this->audit('unknown', null, 'mfa.verify.failed', 'Authentication', 'Verification failed: no pending session.');

            return [
                'valid' => false,
                'message' => 'No pending verification session found. Please login again.',
            ];
        }

        $guard = (string) ($pending['guard'] ?? 'unknown');
        $identifier = (string) ($pending['identifier'] ?? '');
        $allowedMethods = $pending['methods'] ?? ['email'];

        if (!in_array($method, $allowedMethods, true)) {
            $this->audit($guard, $identifier, 'mfa.verify.failed', 'Authentication', 'Verification failed: invalid method selected.');

            return [
                'valid' => false,
                'message' => 'Invalid verification method selected.',
            ];
        }

        $pending['selected_method'] = $method;
        $request->session()->put(self::SESSION_KEY, $pending);

        if (now()->timestamp > (int) $pending['expires_at']) {
            $this->clear($request);
            $this->audit($guard, $identifier, 'mfa.verify.failed', 'Authentication', 'Verification failed: email OTP expired.');

            return [
                'valid' => false,
                'message' => 'Verification code expired. Please login again.',
            ];
        }

        $attempts = ((int) ($pending['attempts'] ?? 0)) + 1;
        $pending['attempts'] = $attempts;
        $request->session()->put(self::SESSION_KEY, $pending);

        if ($attempts > 5) {
            $this->clear($request);
            $this->audit($guard, $identifier, 'mfa.verify.failed', 'Authentication', 'Verification failed: too many invalid email OTP attempts.');

            return [
                'valid' => false,
                'message' => 'Too many invalid attempts. Please login again.',
            ];
        }

        if ($method === 'totp') {
            if (empty($pending['totp_secret'])) {
                $this->audit($guard, $identifier, 'mfa.verify.failed', 'Authentication', 'Verification failed: authenticator is not configured.');

                return [
                    'valid' => false,
                    'message' => 'Authenticator app is not configured for this account.',
                ];
            }

            if (!$this->verifyTotpCode((string) $pending['totp_secret'], $code)) {
                $this->audit($guard, $identifier, 'mfa.verify.failed', 'Authentication', 'Verification failed: invalid authenticator code.');

                return [
                    'valid' => false,
                    'message' => 'Invalid authenticator code. Please try again.',
                ];
            }

            $this->audit($guard, $identifier, 'mfa.verify.success', 'Authentication', 'MFA verified successfully using authenticator app.');

            return [
                'valid' => true,
                'pending' => $pending,
                'method' => 'totp',
            ];
        }

        if (!Hash::check($code, $pending['code_hash'])) {
            $this->audit($guard, $identifier, 'mfa.verify.failed', 'Authentication', 'Verification failed: invalid email OTP code.');

            return [
                'valid' => false,
                'message' => 'Invalid verification code. Please try again.',
            ];
        }

        $this->audit($guard, $identifier, 'mfa.verify.success', 'Authentication', 'MFA verified successfully using email OTP.');

        return [
            'valid' => true,
            'pending' => $pending,
            'method' => 'email',
        ];
    }

    public function resend(Request $request): array
    {
        $pending = $this->getPending($request);

        if (!$pending) {
            return [
                'ok' => false,
                'message' => 'No pending verification session found. Please login again.',
            ];
        }

        $guard = (string) ($pending['guard'] ?? 'unknown');
        $identifier = (string) ($pending['identifier'] ?? '');

        if (now()->timestamp < (int) ($pending['resend_available_at'] ?? 0)) {
            $this->audit($guard, $identifier, 'mfa.resend.blocked', 'Authentication', 'MFA resend blocked due to cooldown.');

            return [
                'ok' => false,
                'message' => 'Please wait before requesting a new code.',
            ];
        }

        $code = $this->generateCode();

        $pending['code_hash'] = Hash::make($code);
        $pending['expires_at'] = now()->addMinutes(5)->timestamp;
        $pending['resend_available_at'] = now()->addSeconds(30)->timestamp;
        $pending['attempts'] = 0;

        $request->session()->put(self::SESSION_KEY, $pending);

        $this->sendCode((string) $pending['email'], $code);

        $this->audit($guard, $identifier, 'mfa.resend.success', 'Authentication', 'MFA email OTP resent successfully.');

        return [
            'ok' => true,
            'message' => 'A new verification code has been sent.',
        ];
    }

    public function logCancellation(Request $request): void
    {
        $pending = $this->getPending($request);

        if (!$pending) {
            return;
        }

        $this->audit(
            (string) ($pending['guard'] ?? 'unknown'),
            (string) ($pending['identifier'] ?? ''),
            'mfa.challenge.cancelled',
            'Authentication',
            'User cancelled MFA verification step.'
        );
    }

    public function hasValidTrustedDevice(Request $request, string $guard, string $identifier): bool
    {
        $payload = $this->readTrustedDeviceCookie($request);

        if (!$payload) {
            return false;
        }

        if ((string) ($payload['guard'] ?? '') !== $guard) {
            return false;
        }

        if ((string) ($payload['identifier'] ?? '') !== $identifier) {
            return false;
        }

        $expiresAt = (int) ($payload['expires_at'] ?? 0);

        if ($expiresAt < now()->timestamp) {
            return false;
        }

        return true;
    }

    public function makeTrustedDeviceCookie(string $guard, string $identifier, ?int $checkedAtTimestamp = null): Cookie
    {
        $checkedAt = $this->resolveRememberCheckedAt($checkedAtTimestamp);
        $expiresAt = $checkedAt->copy()->addMonthNoOverflow()->timestamp;
        $minutes = max(1, (int) now()->diffInMinutes($checkedAt->copy()->addMonthNoOverflow(), false));

        $payload = base64_encode((string) json_encode([
            'guard' => $guard,
            'identifier' => $identifier,
            'expires_at' => $expiresAt,
        ]));

        $this->audit(
            $guard,
            $identifier,
            'mfa.trusted_device.created',
            'Authentication',
            'Trusted device cookie issued for one month starting from checkbox selection time.'
        );

        return cookie()->make(
            self::REMEMBER_COOKIE_KEY,
            $payload,
            $minutes,
            '/',
            null,
            config('session.secure_cookie'),
            true,
            false,
            'lax'
        );
    }

    public function rememberUntilLabel(): string
    {
        return now()->addMonthNoOverflow()->format('F j, Y');
    }

    private function generateCode(): string
    {
        return (string) random_int(100000, 999999);
    }

    private function sendCode(string $email, string $code): void
    {
        $subject = 'Your PUPTracker verification code';
        $message = implode(PHP_EOL, [
            'Hello,',
            '',
            "Your PUPTracker verification code is: {$code}",
            'This code will expire in 5 minutes.',
            '',
            'If you requested this login, enter this code to continue.',
            'If you did not request this, please ignore this email. Do not share this code with anyone.',
            '',
            'For security, PUPTracker support will never ask for your OTP.',
        ]);

        try {
            Mail::raw($message, function ($mail) use ($email, $subject): void {
                $mail->to($email)->subject($subject);
            });
        } catch (\Throwable $exception) {
            if (app()->environment('local', 'testing')) {
                Log::warning('MFA mail sending failed in local/testing, using log fallback.', [
                    'email' => $email,
                    'code' => $code,
                    'error' => $exception->getMessage(),
                ]);

                return;
            }

            throw $exception;
        }
    }

    private function maskEmail(string $email): string
    {
        [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');

        if ($name === '' || $domain === '') {
            return '***';
        }

        $visiblePrefix = substr($name, 0, 1);
        $visibleSuffix = strlen($name) > 2 ? substr($name, -1) : '';
        $maskedMid = str_repeat('*', max(1, strlen($name) - 2));

        return $visiblePrefix . $maskedMid . $visibleSuffix . '@' . $domain;
    }

    private function verifyTotpCode(string $secret, string $code): bool
    {
        if (!preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $decodedSecret = $this->base32Decode($secret);

        if ($decodedSecret === null) {
            return false;
        }

        $timeStep = 30;
        $counter = (int) floor(time() / $timeStep);

        for ($offset = -1; $offset <= 1; $offset++) {
            if (hash_equals($this->hotp($decodedSecret, $counter + $offset), $code)) {
                return true;
            }
        }

        return false;
    }

    private function hotp(string $secret, int $counter): string
    {
        $counterBytes = pack('N*', 0) . pack('N*', $counter);
        $hash = hash_hmac('sha1', $counterBytes, $secret, true);
        $offset = ord(substr($hash, -1)) & 0x0f;
        $segment = substr($hash, $offset, 4);
        $value = unpack('N', $segment)[1] & 0x7fffffff;

        return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
    }

    private function base32Decode(string $input): ?string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $cleanInput = strtoupper(preg_replace('/[^A-Z2-7]/', '', $input) ?? '');

        if ($cleanInput === '') {
            return null;
        }

        $bits = '';
        $output = '';

        for ($i = 0; $i < strlen($cleanInput); $i++) {
            $position = strpos($alphabet, $cleanInput[$i]);

            if ($position === false) {
                return null;
            }

            $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
        }

        $chunks = str_split($bits, 8);

        foreach ($chunks as $chunk) {
            if (strlen($chunk) !== 8) {
                continue;
            }

            $output .= chr(bindec($chunk));
        }

        return $output;
    }

    private function readTrustedDeviceCookie(Request $request): ?array
    {
        $raw = $request->cookie(self::REMEMBER_COOKIE_KEY);

        if (!is_string($raw) || $raw === '') {
            // Legacy compatibility for older cookie name.
            $raw = $request->cookie(self::LEGACY_REMEMBER_COOKIE_KEY);
        }

        if (!is_string($raw) || $raw === '') {
            return null;
        }

        $decoded = base64_decode($raw, true);

        if ($decoded === false) {
            return null;
        }

        $payload = json_decode($decoded, true);

        return is_array($payload) ? $payload : null;
    }

    private function resolveRememberCheckedAt(?int $checkedAtTimestamp)
    {
        if ($checkedAtTimestamp === null) {
            return now();
        }

        $checkedAt = now()->createFromTimestamp($checkedAtTimestamp);
        $minAllowed = now()->subDay();
        $maxAllowed = now()->addMinutes(5);

        if ($checkedAt->lt($minAllowed) || $checkedAt->gt($maxAllowed)) {
            return now();
        }

        return $checkedAt;
    }

    private function audit(string $guard, ?string $identifier, string $action, string $module, string $description): void
    {
        try {
            AuditLog::create([
                'actor_type' => $guard,
                'actor_id' => $this->normalizeActorId($identifier),
                'action' => $action,
                'module' => $module,
                'description' => $description . ' [' . trim($guard . ':' . (string) $identifier, ':') . ']',
                'created_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Unable to write MFA audit log.', [
                'action' => $action,
                'guard' => $guard,
                'identifier' => $identifier,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function normalizeActorId(?string $identifier): ?int
    {
        if ($identifier === null) {
            return null;
        }

        return ctype_digit($identifier) ? (int) $identifier : null;
    }
}
