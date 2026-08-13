# OTP Email Template Testing Guide

## Overview

The OTP email template has been customized to match PUPTracker's brand aesthetics with:

- **Colors**: Maroon (#800000), Dark Maroon (#5f0000), and Gold (#ffdf00)
- **Template**: `resources/views/emails/otp.blade.php`
- **Mailable**: `app/Mail/OtpCode.php`
- **Integration**: Updated `app/Support/MfaService.php` to use the branded template

## Files Created/Modified

### New Files

1. **resources/views/emails/otp.blade.php** - Branded OTP email template
2. **app/Mail/OtpCode.php** - Mailable class for OTP emails

### Modified Files

1. **app/Support/MfaService.php** - Updated `sendCode()` method to use new Mailable

## Testing Steps

### Step 1: Trigger MFA During Admin Login

1. Go to `http://your-app/admin/login`
2. Enter valid admin credentials
3. Observe the MFA verification screen
4. Check your email for the OTP email

### Step 2: Verify Email Template Rendering

The email should display:

- **Header**: Maroon gradient background with "PUPTracker" branding and "Violation Management System" subtitle
- **Main Content**:
    - "Verify Your Account" heading in maroon
    - Descriptive text about one-time passcode
    - Large, prominent OTP code in white text on maroon gradient background
    - Expiry time notification (5 minutes)
    - Support information with security@pup.edu.ph email
- **Footer**: Dark maroon gradient with gold accents, copyright info, and links

### Step 3: Verify OTP Code Format

- OTP should be a 6-digit code (e.g., "987654")
- Code should be displayed with wide letter spacing for readability
- Code should be centered in the box

### Step 4: Verify Responsiveness

Test email rendering in:

- Desktop email clients (Gmail, Outlook, Apple Mail)
- Mobile email apps (Gmail Mobile, Apple Mail on iPhone)
- Web-based email clients
- Email preview tools (Litmus, Email on Acid)

### Step 5: Test Code Expiry Display

The email should show "This code will expire in 5 minutes"

- Verify the expiry time parameter is being passed correctly
- Confirm it displays the exact number from the template variable

### Step 6: Test Support Links

Click the support email link `support@pup.edu.ph` and verify:

- It opens the user's default email client
- Subject line is pre-filled (optional in template)

### Step 7: Dark Mode Support

If testing in email clients with dark mode:

- Text should remain readable (white/light colors adapt)
- Maroon colors should adapt appropriately
- Gold accents should remain visible

### Step 8: Verify MFA Verification Flow

1. Copy the OTP code from the email
2. Return to the login page
3. Enter the OTP code
4. Verify successful login
5. Check audit logs for MFA success entry

## Visual Verification Checklist

- [ ] Header displays with maroon gradient background
- [ ] "PUPTracker" text is visible in gold
- [ ] "Violation Management System" subtitle appears
- [ ] Main heading is maroon colored
- [ ] Descriptive text is clear and readable
- [ ] OTP code displays prominently with proper spacing
- [ ] OTP code background is maroon gradient
- [ ] OTP code text is white/high contrast
- [ ] Expiry notice is clearly visible
- [ ] Support email link is clickable
- [ ] Footer has dark maroon gradient background
- [ ] "PUP Tracker System" branding visible in gold
- [ ] Copyright notice is present
- [ ] Footer links are in gold and clickable
- [ ] Template is responsive on mobile devices
- [ ] Dark mode rendering is acceptable

## Technical Details

### Template Variables

- `$otpCode` - The 6-digit OTP code to display
- `$expiryMinutes` - Time in minutes before code expires (default: 5)
- `$recipientName` - User's name (optional, for future personalization)

### Email Subject

"Verify Your PUPTracker Account - One-Time Passcode"

### Email From

Configured via `.env` - `MAIL_FROM_NAME` and `MAIL_FROM_ADDRESS`

### Email Configuration

Uses Mailgun transport as configured in `config/mail.php`

## Troubleshooting

### Email not arriving

- Check `.env` MAILGUN\_\* variables are correctly configured
- Verify sender email is authorized for Mailgun domain
- Check spam/junk folder
- Review Laravel logs at `storage/logs/`

### Template rendering issues

- Clear browser cache and reload
- Test in multiple email clients
- Check email client's HTML rendering settings
- Verify no third-party email filtering is stripping styles

### OTP code not displaying

- Ensure MfaService is properly passing the `$otpCode` variable
- Check that `Mail::to()->send()` is correctly instantiating OtpCode Mailable
- Review app logs for mail sending errors

### Styling issues

- Email styles are inline for maximum compatibility
- Some email clients strip certain CSS properties
- Test with Email on Acid or Litmus for full compatibility report

## Next Steps

After successful testing:

1. Monitor MFA email delivery in production
2. Collect user feedback on email clarity and styling
3. Consider A/B testing with different color schemes if needed
4. Update this guide with any client-specific rendering issues discovered
