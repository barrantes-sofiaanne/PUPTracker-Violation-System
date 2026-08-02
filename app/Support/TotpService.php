<?php

namespace App\Support;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Str;

class TotpService
{
    private const BACKUP_CODES_COUNT = 10;
    private const BACKUP_CODE_LENGTH = 8;

    /**
     * Generate a new TOTP secret
     */
    public function generateSecret(): string
    {
        return $this->base32Encode(random_bytes(32));
    }

    /**
     * Get QR code for TOTP setup
     */
    public function getQRCode(string $email, string $appName, string $secret): string
    {
        $otpauthUrl = $this->getOtpAuthUrl($email, $appName, $secret);

        $options = new QROptions([
            'version'         => 5,
            'outputInterface' => QRMarkupSVG::class,
            'eccLevel'        => EccLevel::L,
        ]);

        return (new QRCode($options))->render($otpauthUrl);
    }

    /**
     * Get otpauth URL for manual entry
     */
    public function getOtpAuthUrl(string $email, string $appName, string $secret): string
    {
        $issuer = 'PUPTracker';
        $label = urlencode("{$appName} ({$email})");

        return sprintf(
            'otpauth://totp/%s?secret=%s&issuer=%s&algorithm=SHA1&digits=6&period=30',
            $label,
            $secret,
            urlencode($issuer)
        );
    }

    /**
     * Generate backup codes for account recovery
     */
    public function generateBackupCodes(int $count = self::BACKUP_CODES_COUNT): array
    {
        $codes = [];

        for ($i = 0; $i < $count; $i++) {
            $codes[] = $this->generateBackupCode();
        }

        return $codes;
    }

    /**
     * Verify a TOTP code
     */
    public function verify(string $secret, string $code, int $windowSize = 1): bool
    {
        if (!preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $decodedSecret = $this->base32Decode($secret);

        if ($decodedSecret === null) {
            return false;
        }

        $timeStep = 30;
        $currentTime = (int) floor(time() / $timeStep);

        // Check current time and surrounding windows
        for ($offset = -$windowSize; $offset <= $windowSize; $offset++) {
            $expectedCode = $this->generateCode($decodedSecret, $currentTime + $offset);

            if (hash_equals($expectedCode, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get current TOTP code (for testing/debugging only)
     */
    public function getCurrentCode(string $secret): ?string
    {
        $decodedSecret = $this->base32Decode($secret);

        if ($decodedSecret === null) {
            return null;
        }

        $timeStep = 30;
        $counter = (int) floor(time() / $timeStep);

        return $this->generateCode($decodedSecret, $counter);
    }

    /**
     * Store backup codes (hashed)
     */
    public function hashBackupCodes(array $codes): array
    {
        return array_map(fn ($code) => password_hash($code, PASSWORD_BCRYPT), $codes);
    }

    /**
     * Verify a backup code
     */
    public function verifyBackupCode(string $code, array $hashedCodes): bool
    {
        foreach ($hashedCodes as $hashedCode) {
            if (password_verify($code, $hashedCode)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate a single backup code
     */
    private function generateBackupCode(): string
    {
        $code = '';

        for ($i = 0; $i < self::BACKUP_CODE_LENGTH; $i++) {
            $code .= random_int(0, 9);
        }

        // Format as XXXX-XXXX for readability
        return substr($code, 0, 4) . '-' . substr($code, 4);
    }

    /**
     * Generate HOTP/TOTP code
     */
    private function generateCode(string $secret, int $counter): string
    {
        $counterBytes = pack('N*', 0) . pack('N*', $counter);
        $hash = hash_hmac('sha1', $counterBytes, $secret, true);
        $offset = ord(substr($hash, -1)) & 0x0f;
        $segment = substr($hash, $offset, 4);
        $value = unpack('N', $segment)[1] & 0x7fffffff;

        return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Base32 encoding
     */
    private function base32Encode(string $input): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        $output = '';

        for ($i = 0; $i < strlen($input); $i++) {
            $bits .= str_pad(decbin(ord($input[$i])), 8, '0', STR_PAD_LEFT);
        }

        // Pad bits to multiple of 5
        while (strlen($bits) % 5 !== 0) {
            $bits .= '0';
        }

        // Convert 5-bit chunks to base32
        for ($i = 0; $i < strlen($bits); $i += 5) {
            $chunk = substr($bits, $i, 5);
            $output .= $alphabet[bindec($chunk)];
        }

        return $output;
    }

    /**
     * Base32 decoding
     */
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
}
