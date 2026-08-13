# TOTP Two-Factor Authentication Implementation

## Overview

Comprehensive TOTP (Time-based One-Time Password) implementation for admin and security staff with:

- ✅ Authenticator app support (Google Authenticator, Microsoft Authenticator, Authy, etc.)
- ✅ QR code generation for easy setup
- ✅ Backup codes for account recovery (10 codes, 8-digit format)
- ✅ Seamless integration with existing email OTP
- ✅ Audit logging for all TOTP operations
- ✅ Session management and security

## Files Created

### Core Services

1. **`app/Support/TotpService.php`** (6KB)
    - TOTP secret generation and verification
    - QR code generation via chillerlan/php-qrcode
    - Backup code generation and verification
    - Base32 encoding/decoding for standards compliance
    - Current code generation (for testing)

2. **`app/Http/Controllers/Auth/TotpSetupController.php`** (6.2KB)
    - Setup page display with QR code and manual entry
    - Code verification during setup
    - Backup codes generation and display
    - TOTP enablement/disablement
    - Password verification for security

### Views

3. **`resources/views/auth/totp-setup.blade.php`** (6.2KB)
    - QR code display
    - Manual key entry fallback
    - Code verification form
    - Responsive Bootstrap design
    - Security warnings

4. **`resources/views/auth/totp-backup-codes.blade.php`** (6KB)
    - Backup codes display
    - Print functionality
    - Download as text file
    - Security warnings
    - Confirmation form

### Database

5. **`database/migrations/2026_08_02_235314_add_backup_codes_to_auth_tables.php`**
    - Adds `mfa_backup_codes` (JSON) to store hashed backup codes
    - Adds `mfa_backup_codes_used` (JSON) to track used codes
    - Applied to users_tbl, admins, and security tables

### Routes

6. **Routes added to `routes/web.php`**
    - `GET /totp/setup` - Show setup page
    - `POST /totp/verify` - Verify and enable TOTP
    - `GET /totp/backup-codes` - Display backup codes
    - `POST /totp/confirm-backup-codes` - Acknowledge backup codes
    - `POST /totp/disable` - Disable TOTP

### Dependencies

7. **Composer Package**
    - `chillerlan/php-qrcode` (^6.0) - QR code generation

## Architecture

### TOTP Flow

```
┌─────────────────────────────────────────────┐
│ Admin/Security Staff                        │
└──────────────────┬──────────────────────────┘
                   │
                   ▼
        ┌──────────────────────┐
        │  Setup Form          │
        │ - QR Code            │
        │ - Manual Secret      │
        └──────────┬───────────┘
                   │
                   ▼
    ┌──────────────────────────────┐
    │ Scan with Authenticator App  │
    └──────────────┬───────────────┘
                   │
                   ▼
        ┌──────────────────────┐
        │  Enter 6-Digit Code  │
        │  TotpService.verify()│
        └──────────┬───────────┘
                   │
        ┌──────────┴──────────┐
        │                     │
        ▼                     ▼
      ✅ Valid            ❌ Invalid
        │                     │
        ▼                     ▼
   Generate Backup    Retry (max 5x)
   Codes
        │
        ▼
   Save & Display
   Backup Codes
        │
        ▼
   TOTP Enabled ✅
```

### Login with TOTP

```
┌──────────────────────────────────┐
│ Admin/Security Login             │
│ Email + Password                 │
└────────────────┬─────────────────┘
                 │
                 ▼
        ┌────────────────────┐
        │ MfaService.begin() │
        │ Send Email OTP     │
        └────────┬───────────┘
                 │
                 ▼
    ┌────────────────────────────┐
    │ User Selects MFA Method    │
    │ - Email OTP                │
    │ - TOTP (if enabled)        │
    │ - Backup Code (recovery)   │
    └────────┬───────────────────┘
             │
    ┌────────┴─────────────┐
    │                      │
    ▼                      ▼
  Email OTP          TOTP/Backup
  Verify             TotpService.verify()
    │                      │
    └──────────┬───────────┘
               │
               ▼
         ✅ Access Granted
```

## Security Features

### TOTP

- ✅ HMAC-SHA1 based (RFC 6238 compliant)
- ✅ 6-digit codes with 30-second time windows
- ✅ ±1 window tolerance for clock skew
- ✅ Code expires immediately after use
- ✅ Audit logged for all verification attempts

### Backup Codes

- ✅ 10 codes per account
- ✅ 8-digit format (XXXX-XXXX for readability)
- ✅ Bcrypt-hashed in database
- ✅ Tracked usage to prevent reuse
- ✅ Cannot be viewed after setup (only during setup)
- ✅ Single-use enforcement

### Setup Process

- ✅ Session-based secret storage during setup
- ✅ Code verification required before activation
- ✅ Password verification required to disable TOTP
- ✅ Comprehensive audit logging
- ✅ Security warnings about backup code storage

## Configuration

### Environment

No special `.env` variables needed. Uses existing auth guards:

- `admin` guard - for admin users
- `security` guard - for security staff

### Authenticator Apps Supported

- Google Authenticator
- Microsoft Authenticator
- Authy
- FreeOTP
- 1Password
- LastPass
- Dashlane
- Any RFC 6238-compliant app

### Database Columns Added

```sql
-- To users_tbl, admins, security tables:
mfa_totp_secret         TEXT     -- TOTP secret (base32 encoded)
mfa_totp_enabled        BOOLEAN  -- Is TOTP active? (default: false)
mfa_backup_codes        JSON     -- Array of hashed backup codes
mfa_backup_codes_used   JSON     -- Array of used backup code hashes
```

## Testing Guide

### Step 1: Install Migration

```bash
php artisan migrate --force
```

### Step 2: Access Setup

1. Login as admin or security staff
2. Visit: `/totp/setup?guard=admin` or `/totp/setup?guard=security`

### Step 3: Setup TOTP

1. Open authenticator app on phone
2. Scan QR code OR manually enter secret
3. Enter 6-digit code shown in app
4. Click "Verify & Enable TOTP"

### Step 4: Save Backup Codes

1. View generated backup codes
2. Print, download, or copy codes
3. Store securely (NOT on computer/phone)
4. Click "I've Saved My Backup Codes"

### Step 5: Test Login

1. Logout
2. Login with email + password
3. Select TOTP method at MFA screen
4. Enter code from authenticator app
5. Verify successful login

### Step 6: Test Backup Code Recovery

1. Logout
2. Login with email + password
3. Select TOTP method at MFA screen
4. Enter one of your backup codes instead of app code
5. Verify login succeeds
6. Note: Used code is now marked as used

### Step 7: Disable TOTP

1. Settings page (to be implemented)
2. Enter password to confirm
3. TOTP and backup codes are removed

## API Reference

### TotpService

```php
// Generate new secret
$secret = $totpService->generateSecret();

// Get QR code SVG
$qrCode = $totpService->getQRCode($email, $appName, $secret);

// Get manual entry URL
$url = $totpService->getOtpAuthUrl($email, $appName, $secret);

// Verify code (returns bool)
$valid = $totpService->verify($secret, '123456', $windowSize = 1);

// Get current code (testing only)
$code = $totpService->getCurrentCode($secret);

// Generate backup codes
$codes = $totpService->generateBackupCodes(10);

// Hash backup codes for storage
$hashed = $totpService->hashBackupCodes($codes);

// Verify backup code
$valid = $totpService->verifyBackupCode('12345678', $hashedCodes);
```

### MfaService Integration

The existing `MfaService` already includes:

- `verifyTotpCode()` - Verify TOTP during login
- `hotp()` - HOTP algorithm
- `base32Decode()` - Base32 decoding

Now includes TotpService for full TOTP support.

## Audit Logging

All TOTP operations are logged to `audit_logs` table:

```
Actions logged:
- totp.setup.started          - User starts setup
- totp.setup.verified         - Setup verified with code
- totp.backup_codes.generated - Backup codes created
- totp.enabled                - TOTP activated
- totp.disabled               - TOTP deactivated
- mfa.verify.success (totp)   - Login successful with TOTP
- mfa.verify.failed (totp)    - Login failed, invalid code
```

## Next Steps / TODO

1. **Settings Dashboard** - Add TOTP management to admin/security settings
2. **Recovery Interface** - Allow users to regenerate backup codes
3. **Device Management** - Track which devices have TOTP enabled
4. **Emergency Access** - Super-admin override for locked accounts
5. **Backup Code Email** - Optionally email backup codes after setup
6. **2FA Status Page** - Show all active 2FA methods

## Troubleshooting

### QR Code Not Loading

- Check `chillerlan/php-qrcode` is installed
- Verify `public/` folder permissions

### Code Always Invalid

- Check server time synchronization (NTP)
- Check app time synchronization
- Try code from ±1 time window

### Backup Codes Not Saving

- Check database migration ran successfully
- Verify `mfa_backup_codes` column exists
- Check database permissions

### Session Expires During Setup

- Setup process stores secret in session for 10 minutes
- If expires, restart setup from `/totp/setup`
- Secret is NOT saved until code is verified

## Security Considerations

1. **Never log TOTP secrets** - Secrets are never logged, only hashed
2. **Backup codes offline** - Should be stored offline, not digital
3. **Unique per user** - Each user has unique secret and backup codes
4. **Time-based** - Codes expire after 30 seconds
5. **No backup codes in email** - Codes are only shown once during setup
6. **Audit trail** - All attempts logged for forensics
7. **Rate limiting** - 6 attempts per minute, 3 resends per minute

## Compliance

- ✅ RFC 6238 (TOTP)
- ✅ RFC 4648 (Base32)
- ✅ NIST SP 800-63-3 (Authentication & Lifecycle)
- ✅ OWASP Top 10 (MFA for sensitive operations)
