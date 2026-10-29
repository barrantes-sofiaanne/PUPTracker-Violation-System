
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="color-scheme" content="light">
    <title>Reset Your PUPTracker Password</title>
</head>

<body style="margin:0;padding:0;background-color:#f2f0ec;font-family:Arial,Helvetica,sans-serif;color:#262626;">

    <!-- Hidden email preview text -->
    <div style="display:none;font-size:1px;color:#f2f0ec;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;">
        Follow the secure link to reset your PUPTracker password.
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
           border="0" style="background-color:#f2f0ec;">
        <tr>
            <td align="center" style="padding:24px 12px;">

                <table role="presentation" width="600" cellpadding="0"
                       cellspacing="0" border="0"
                       style="width:100%;max-width:600px;background-color:#ffffff;border-collapse:separate;border-spacing:0;">

                    <!-- University-inspired header -->
                    <tr>
                        <td style="background-color:#760000;padding:22px 28px;border-bottom:5px solid #d5a62e;">
                            <table role="presentation" width="100%" cellpadding="0"
                                   cellspacing="0" border="0">
                                <tr>
                                    <td width="76" valign="middle"
                                        style="width:76px;padding-right:14px;">
                                        <img
                                            src='{{ rtrim(config('app.url'), '/') }}assets/images/SystemLogo_w/name.png'
                                            width="66"
                                            height="66"
                                            alt="PUPTracker Violation System Logo"
                                            style="display:block;width:66px;height:66px;border:0;">
                                    </td>

                                    <td valign="middle">
                                        <div style="font-size:25px;line-height:1.2;font-weight:bold;color:#ffffff;">
                                            PUPTracker
                                        </div>

                                        <div style="font-size:11px;line-height:1.5;letter-spacing:1px;color:#f7e8b5;">
                                            VIOLATION MANAGEMENT SYSTEM
                                        </div>

                                        <div style="font-size:12px;line-height:1.5;color:#ffffff;margin-top:5px;">
                                            Polytechnic University of the Philippines
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Email body -->
                    <tr>
                        <td style="padding:30px 28px 26px;">

                            <div style="font-size:11px;line-height:1.4;letter-spacing:1.5px;font-weight:bold;color:#8a0000;margin-bottom:8px;">
                                ACCOUNT SECURITY
                            </div>

                            <h1 style="font-size:28px;line-height:1.25;color:#760000;margin:0 0 16px;font-weight:bold;">
                                Reset Your PUPTracker Password
                            </h1>

                            <p style="font-size:16px;line-height:1.7;color:#333333;margin:0 0 14px;">
                                Hello,
                            </p>

                            <p style="font-size:16px;line-height:1.7;color:#333333;margin:0 0 22px;">
                                We received a request to reset the password for your PUPTracker account.
                                If you made this request, select the button below to create a new password.
                            </p>

                            <!-- Reset button and campus photo -->
                            <table role="presentation" width="100%" cellpadding="0"
                                   cellspacing="0" border="0"
                                   style="border:1px solid #eadfda;border-radius:8px;border-collapse:separate;">

                                <tr>
                                    <td valign="middle"
                                        style="padding:20px 18px;background-color:#fffaf2;">

                                        <p style="font-size:14px;line-height:1.6;color:#5a2525;margin:0 0 16px;">
                                            Use the secure link to choose a new password.
                                            For your protection, the link expires after
                                            <strong>{{ $expiryMinutes }} minutes</strong>
                                            and can only be used once.
                                        </p>

                                        <table role="presentation" cellpadding="0"
                                               cellspacing="0" border="0">
                                            <tr>
                                                <td align="center" bgcolor="#800000"
                                                    style="border-radius:5px;">
                                                    <a href="{{ $resetUrl }}"
                                                       target="_blank"
                                                       style="display:inline-block;padding:15px 25px;font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.2;font-weight:bold;color:#ffffff;text-decoration:none;border-radius:5px;">
                                                        Reset Password &nbsp; &#8594;
                                                    </a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>

                                    <td width="190" valign="middle"
                                        style="width:190px;background-color:#f4eee6;">
                                        <img
                                            src='{{ rtrim(config('app.url'), '/') }}assets/images/PUP-TAGUIG/PUPT-picture.jpg'
                                            width="190"
                                            alt="PUP campus"
                                            style="display:block;width:190px;max-width:100%;height:auto;border:0;">
                                    </td>
                                </tr>
                            </table>

                            <!-- Security notice -->
                            <table role="presentation" width="100%" cellpadding="0"
                                   cellspacing="0" border="0"
                                   style="margin-top:22px;background-color:#fff4d8;border-left:4px solid #d5a62e;">
                                <tr>
                                    <td style="padding:14px;">
                                        <p style="font-size:14px;line-height:1.6;color:#5b430e;margin:0;">
                                            <strong>Didn't request a password reset?</strong><br>
                                            You can safely ignore this email.
                                            Your password will remain unchanged.
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <!-- Alternative reset link -->
                            <p style="font-size:13px;line-height:1.6;color:#666666;margin:22px 0 0;">
                                If the button does not work, copy and paste this link into your browser:
                            </p>

                            <p style="font-size:12px;line-height:1.6;word-break:break-all;margin:6px 0 0;">
                                <a href="{{ $resetUrl }}"
                                   target="_blank"
                                   style="color:#800000;text-decoration:underline;">
                                    {{ $resetUrl }}
                                </a>
                            </p>

                            <p style="font-size:14px;line-height:1.6;color:#555555;margin:22px 0 0;">
                                Need help?
                                Contact
                                <a href="mailto:contact.puptrackervs@gmail.com"
                                   style="color:#800000;font-weight:bold;text-decoration:underline;">
                                    contact.puptrackervs@gmail.com
                                </a>.
                            </p>
                        </td>
                    </tr>

                    <!-- University footer -->
                    <tr>
                        <td align="center"
                            style="background-color:#5f0000;border-top:4px solid #d5a62e;padding:22px 24px;">

                            <div style="font-size:15px;line-height:1.4;font-weight:bold;color:#ffffff;">
                                PUPTracker
                            </div>

                            <div style="font-size:12px;line-height:1.6;color:#f7e8b5;">
                                Violation Management System
                            </div>

                            <div style="font-size:12px;line-height:1.7;color:#ffffff;margin-top:8px;">
                                Polytechnic University of the Philippines
                            </div>

                            <div style="font-size:12px;line-height:1.7;color:#ffffff;">
                                Discipline Today. A Brighter Tomorrow.
                            </div>

                            <div style="font-size:11px;line-height:1.7;color:#eadfda;margin-top:12px;">
                                <a href="https://www.pup.edu.ph/privacy/"
                                   style="color:#f7e8b5;text-decoration:underline;">
                                    Privacy Policy
                                </a>

                                &nbsp; | &nbsp;

                                <a href="mailto:contact.puptrackervs@gmail.com"
                                   style="color:#f7e8b5;text-decoration:underline;">
                                    Contact Support
                                </a>
                            </div>
                        </td>
                    </tr>
                </table>

                <p style="max-width:600px;font-size:11px;line-height:1.5;color:#777777;text-align:center;margin:14px auto 0;">
                    This is an automated security message from PUPTracker.
                    Please do not reply directly to this email.
                </p>

            </td>
        </tr>
    </table>

</body>
</html>
