<?php

namespace App\Support;

use App\Mail\OtpCode;
use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\Security;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class MfaService
{
    private const SESSION_KEY = 'mfa.pending';

    public function __construct(
        private TotpService $totpService = new TotpService(),
    ) {}

    public function beginChallenge(
        Request $request,
        string $guard,
        string $identifier,
        string $email,
        ?string $totpSecret = null,
        bool $totpEnabled = false,
        ?string $backupCodesJson = null,
        ?string $backupCodesUsedJson = null
    ): void
    {
        $code = $this->generateCode();
        $hasTotp = !empty($totpSecret);
        $backupCodes = $this->decodeJsonArray($backupCodesJson);
        $backupCodesUsed = $this->decodeJsonArray($backupCodesUsedJson);
        $hasBackupCodes = !empty($backupCodes);

        $methods = ['email'];
        if ($hasTotp) {
            $methods[] = 'totp';
        }
        if ($hasBackupCodes) {
            $methods[] = 'backup';
        }

        $request->session()->put(self::SESSION_KEY, [
            'guard' => $guard,
            'identifier' => $identifier,
            'email' => $email,
            'email_masked' => $this->maskEmail($email),
            'totp_secret' => $hasTotp ? $totpSecret : null,
            'methods' => $methods,
            'selected_method' => 'email',
            'backup_codes' => $hasBackupCodes ? $backupCodes : [],
            'backup_codes_used' => $hasBackupCodes ? $backupCodesUsed : [],
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
        $code = trim($code);

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

        if ($method === 'backup') {
            if (!$this->consumeBackupCode($guard, $identifier, $code, $pending)) {
                $this->audit($guard, $identifier, 'mfa.verify.failed', 'Authentication', 'Verification failed: invalid backup code.');

                return [
                    'valid' => false,
                    'message' => 'Invalid backup code. Please try again.',
                ];
            }

            $request->session()->put(self::SESSION_KEY, $pending);
            $this->audit($guard, $identifier, 'mfa.verify.success', 'Authentication', 'MFA verified successfully using backup code.');

            return [
                'valid' => true,
                'pending' => $pending,
                'method' => 'backup',
            ];
        }

        if (!preg_match('/^\d{6}$/', $code)) {
            $this->audit($guard, $identifier, 'mfa.verify.failed', 'Authentication', 'Verification failed: invalid email OTP format.');

            return [
                'valid' => false,
                'message' => 'Email OTP must be a 6-digit code.',
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

    private function generateCode(): string
    {
        return (string) random_int(100000, 999999);
    }

    private function sendCode(string $email, string $code): void
    {
        try {
            Mail::to($email)->send(new OtpCode($code, expiryMinutes: 5));
        } catch (\Throwable $exception) {
            if (app()->environment('local', 'testing')) {
                Log::warning('MFA mail sending failed in local/testing, using log fallback.', [
                    'email' => $email,
                    'code' => $code,
                    'error' => $exception->getMessage(),
                ]);

                return;
            }

            Log::error('MFA email send failed', [
                'email' => $email,
                'error' => $exception->getMessage(),
            ]);
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

    private function consumeBackupCode(string $guard, string $identifier, string $code, array &$pending): bool
    {
        $backupCodes = is_array($pending['backup_codes'] ?? null) ? $pending['backup_codes'] : [];
        $backupCodesUsed = is_array($pending['backup_codes_used'] ?? null) ? $pending['backup_codes_used'] : [];

        if (empty($backupCodes)) {
            return false;
        }

        $digitsOnly = preg_replace('/\D/', '', $code) ?? '';
        if (!preg_match('/^\d{8}$/', $digitsOnly)) {
            return false;
        }

        $normalizedCode = substr($digitsOnly, 0, 4) . '-' . substr($digitsOnly, 4, 4);
        $matchedIndex = null;

        foreach ($backupCodes as $index => $hashedCode) {
            if (is_string($hashedCode) && password_verify($normalizedCode, $hashedCode)) {
                $matchedIndex = $index;
                break;
            }
        }

        if ($matchedIndex === null) {
            return false;
        }

        unset($backupCodes[$matchedIndex]);
        $backupCodes = array_values($backupCodes);
        $backupCodesUsed[] = $normalizedCode;

        $user = $this->resolveUserForGuard($guard, $identifier);
        if (!$user) {
            return false;
        }

        if (!MfaSchema::supportsBackupCodes($user)) {
            Log::warning('Backup code consumption skipped because MFA backup-code columns are missing', [
                'guard' => $guard,
                'identifier' => $identifier,
            ]);

            return false;
        }

        $user->mfa_backup_codes = json_encode($backupCodes);
        $user->mfa_backup_codes_used = json_encode($backupCodesUsed);

        try {
            $user->save();
        } catch (QueryException $exception) {
            if (!MfaSchema::isMissingColumnException($exception)) {
                throw $exception;
            }

            MfaSchema::forgetBackupCodeAttributes($user);

            Log::warning('Backup code consumption skipped because MFA backup-code columns are missing', [
                'guard' => $guard,
                'identifier' => $identifier,
            ]);

            return false;
        }

        $pending['backup_codes'] = $backupCodes;
        $pending['backup_codes_used'] = $backupCodesUsed;

        return true;
    }

    private function decodeJsonArray(?string $value): array
    {
        if (!is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function resolveUserForGuard(string $guard, string $identifier): Admin|Security|User|null
    {
        return match ($guard) {
            'admin' => Admin::find($identifier),
            'security' => Security::find($identifier),
            'student' => User::where('student_number', $identifier)->first(),
            default => null,
        };
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
