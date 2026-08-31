<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Reset your password - PUPTracker</title>
    <style>
        body { margin: 0 !important; padding: 0 !important; background-color: #f5f5f5; font-family: Arial, sans-serif; line-height: 1.4; }
        .email-container { max-width: 600px; margin: 0 auto; background-color: #ffffff; }
        .header { padding: 40px 30px 30px; text-align: left; background: linear-gradient(135deg, #800000 0%, #5f0000 100%); }
        .main-content { padding: 0 30px 40px; text-align: center; }
        .main-heading { font-size: 28px; font-weight: bold; color: #800000; margin: 0 0 15px; line-height: 1.2; }
        .description { font-size: 16px; color: #333333; margin: 0 0 25px; line-height: 1.6; }
        .reset-button {
            display: inline-block; margin: 20px 0 10px; padding: 14px 36px;
            background: linear-gradient(135deg, #800000 0%, #5f0000 100%);
            color: #ffffff !important; font-size: 16px; font-weight: bold;
            text-decoration: none; border-radius: 8px;
        }
        .expiry-notice { font-size: 14px; color: #666666; margin: 25px 0 20px; line-height: 1.6; font-style: italic; }
        .support-text { font-size: 14px; color: #666666; margin: 20px 0 0; line-height: 1.6; }
        .support-link { color: #800000; text-decoration: underline; font-weight: bold; }
        .footer { background: linear-gradient(135deg, #5f0000 0%, #800000 100%); padding: 30px 30px; text-align: center; }
        .footer-brand { color: #ffdf00; font-size: 12px; font-weight: bold; margin: 0 0 15px; text-transform: uppercase; letter-spacing: 1px; }
        .copyright { color: #f5f5f5; font-size: 12px; margin: 0 0 8px; }
        .address { color: #f5f5f5; font-size: 12px; margin: 0 0 15px; }
        .footer-links a { color: #ffdf00; text-decoration: none; font-size: 12px; font-weight: bold; }
        .footer-separator { color: #f5f5f5; margin: 0 5px; }
        @media only screen and (max-width: 600px) {
            .email-container { width: 100% !important; max-width: 100% !important; }
            .header, .main-content { padding-left: 20px !important; padding-right: 20px !important; }
            .main-heading { font-size: 24px !important; }
        }
        @media (prefers-color-scheme: dark) {
            body { background-color: #1a1a1a !important; }
            .email-container { background-color: #2d2d2d !important; }
            .main-heading { color: #ffb6b6 !important; }
            .description, .expiry-notice, .support-text { color: #cccccc !important; }
            .support-link { color: #ffdf00 !important; }
        }
    </style>
</head>
<body>
    <table role="presentation" class="email-container" align="center" cellpadding="0" cellspacing="0" border="0" style="max-width: 600px; margin: 0 auto; background-color: #ffffff;">
        <tr>
            <td class="header" style="padding: 40px 30px 30px; text-align: left; background: linear-gradient(135deg, #800000 0%, #5f0000 100%);">
                <div style="font-size: 24px; font-weight: bold; color: #ffdf00; font-family: Arial, sans-serif;">PUPTracker</div>
                <div style="font-size: 12px; color: #f5f5f5; margin-top: 5px; font-family: Arial, sans-serif;">Violation System</div>
            </td>
        </tr>
        <tr>
            <td class="main-content" style="padding: 40px 30px; text-align: center;">
                <h1 class="main-heading" style="font-size: 28px; font-weight: bold; color: #800000; margin: 0 0 15px; line-height: 1.2; font-family: Arial, sans-serif;">Password Reset Request</h1>

                <p class="description" style="font-size: 16px; color: #333333; margin: 0 0 25px; line-height: 1.6; font-family: Arial, sans-serif;">
                    Someone requested a password reset for your PUPTracker account. If this was you, click the button below to set a new password.
                </p>

                <a href="{{ $resetUrl }}" class="reset-button" style="display: inline-block; margin: 20px 0 10px; padding: 14px 36px; background: linear-gradient(135deg, #800000 0%, #5f0000 100%); color: #ffffff !important; font-size: 16px; font-weight: bold; text-decoration: none; border-radius: 8px; font-family: Arial, sans-serif;">
                    Reset Password
                </a>

                <p class="expiry-notice" style="font-size: 14px; color: #666666; margin: 25px 0 20px; line-height: 1.6; font-family: Arial, sans-serif; font-style: italic;">
                    This link expires in {{ $expiryMinutes }} minutes and can only be used once.
                </p>

                <p class="support-text" style="font-size: 14px; color: #666666; margin: 20px 0 0; line-height: 1.6; font-family: Arial, sans-serif;">
                    If you did not request this, ignore this email. Contact our support team at
                    <a href="mailto:puptrackervs@gmail.com" class="support-link" style="color: #800000; text-decoration: underline; font-weight: bold;">puptrackervs@gmail.com</a>
                    if you need further assistance.
                </p>
            </td>
        </tr>
        <tr>
            <td class="footer" style="background: linear-gradient(135deg, #5f0000 0%, #800000 100%); padding: 30px 30px; text-align: center;">
                <div class="footer-brand" style="color: #ffdf00; font-size: 12px; font-weight: bold; margin: 0 0 15px; text-transform: uppercase; letter-spacing: 1px; font-family: Arial, sans-serif;">PUP Tracker System</div>
                <p class="copyright" style="color: #f5f5f5; font-size: 12px; margin: 0 0 8px; font-family: Arial, sans-serif;">© Polytechnic University of the Philippines. All Rights Reserved</p>
                <p class="address" style="color: #f5f5f5; font-size: 12px; margin: 0 0 15px; font-family: Arial, sans-serif;">PUP Taguig Branch, Philippines</p>
                <p class="footer-links" style="margin: 0;">
                    <a href="mailto:security@pup.edu.ph" style="color: #ffdf00; text-decoration: none; font-size: 12px; font-weight: bold; font-family: Arial, sans-serif;">Contact Us</a>
                    <span class="footer-separator" style="color: #f5f5f5; margin: 0 5px;">|</span>
                    <a href="https://www.pup.edu.ph/privacy/" style="color: #ffdf00; text-decoration: none; font-size: 12px; font-weight: bold; font-family: Arial, sans-serif;">Privacy Policy</a>
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
