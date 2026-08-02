<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Verify your account - PUPTracker</title>
    <!--[if mso]>
    <xml>
        <o:OfficeDocumentSettings>
            <o:AllowPNG/>
            <o:PixelsPerInch>96</o:PixelsPerInch>
        </o:OfficeDocumentSettings>
    </xml>
    <![endif]-->
    <style>
        /* Reset styles */
        body, table, td, p, a, li, blockquote {
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }
        table, td {
            mso-table-lspace: 0pt;
            mso-table-rspace: 0pt;
        }
        img {
            -ms-interpolation-mode: bicubic;
            border: 0;
            height: auto;
            line-height: 100%;
            outline: none;
            text-decoration: none;
        }

        /* Main styles */
        body {
            margin: 0 !important;
            padding: 0 !important;
            background-color: #f5f5f5;
            font-family: Arial, sans-serif;
            line-height: 1.4;
        }

        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
        }

        .header {
            padding: 40px 30px 30px;
            text-align: left;
            background: linear-gradient(135deg, #800000 0%, #5f0000 100%);
        }

        .logo {
            display: block;
            max-width: 120px;
            height: auto;
        }

        .main-content {
            padding: 0 30px 40px;
            text-align: center;
        }

        .phone-icon {
            display: block;
            margin: 0 auto 40px;
            max-width: 180px;
            height: auto;
        }

        .main-heading {
            font-size: 32px;
            font-weight: bold;
            color: #800000;
            margin: 0 0 15px;
            line-height: 1.2;
        }

        .description {
            font-size: 16px;
            color: #333333;
            margin: 0 0 30px;
            line-height: 1.6;
        }

        .otp-code {
            font-size: 48px;
            font-weight: bold;
            color: #ffffff;
            background: linear-gradient(135deg, #800000 0%, #5f0000 100%);
            margin: 30px 0;
            padding: 25px;
            letter-spacing: 8px;
            font-family: 'Courier New', monospace, Arial;
            border-radius: 8px;
        }

        .expiry-notice {
            font-size: 14px;
            color: #666666;
            margin: 30px 0 20px;
            line-height: 1.6;
            font-style: italic;
        }

        .support-text {
            font-size: 14px;
            color: #666666;
            margin: 20px 0 0;
            line-height: 1.6;
        }

        .support-link {
            color: #800000;
            text-decoration: underline;
            font-weight: bold;
        }

        .footer {
            background: linear-gradient(135deg, #5f0000 0%, #800000 100%);
            padding: 30px 30px;
            text-align: center;
        }

        .footer-logo {
            display: block;
            margin: 0 auto 15px;
            max-width: 100px;
            height: auto;
        }

        .footer-brand {
            color: #ffdf00;
            font-size: 12px;
            font-weight: bold;
            margin: 0 0 15px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .copyright {
            color: #f5f5f5;
            font-size: 12px;
            margin: 0 0 8px;
        }

        .address {
            color: #f5f5f5;
            font-size: 12px;
            margin: 0 0 15px;
        }

        .footer-links {
            margin: 0;
        }

        .footer-links a {
            color: #ffdf00;
            text-decoration: none;
            font-size: 12px;
            font-weight: bold;
        }

        .footer-links a:hover {
            text-decoration: underline;
        }

        .footer-separator {
            color: #f5f5f5;
            margin: 0 5px;
        }

        /* Responsive styles */
        @media only screen and (max-width: 600px) {
            .email-container {
                width: 100% !important;
                max-width: 100% !important;
            }

            .header,
            .main-content {
                padding-left: 20px !important;
                padding-right: 20px !important;
            }

            .main-heading {
                font-size: 26px !important;
                line-height: 1.3 !important;
            }

            .description,
            .expiry-notice,
            .support-text {
                font-size: 14px !important;
            }

            .otp-code {
                font-size: 36px !important;
                letter-spacing: 4px !important;
                padding: 20px !important;
            }

            .footer {
                padding: 20px 20px !important;
            }
        }

        /* Dark mode support */
        @media (prefers-color-scheme: dark) {
            body {
                background-color: #1a1a1a !important;
            }
            .email-container {
                background-color: #2d2d2d !important;
            }
            .main-heading {
                color: #ffb6b6 !important;
            }
            .description,
            .expiry-notice,
            .support-text {
                color: #cccccc !important;
            }
            .support-link {
                color: #ffdf00 !important;
            }
        }
    </style>
</head>
<body>
    <!--[if mso]>
    <table role="presentation" width="600" align="center" cellpadding="0" cellspacing="0" border="0">
    <tr><td>
    <![endif]-->

    <table role="presentation" class="email-container" align="center" cellpadding="0" cellspacing="0" border="0" style="max-width: 600px; margin: 0 auto; background-color: #ffffff;">
        <!-- Header -->
        <tr>
            <td class="header" style="padding: 40px 30px 30px; text-align: left; background: linear-gradient(135deg, #800000 0%, #5f0000 100%);">
                <div style="font-size: 24px; font-weight: bold; color: #ffdf00; font-family: Arial, sans-serif;">
                    PUPTracker
                </div>
                <div style="font-size: 12px; color: #f5f5f5; margin-top: 5px; font-family: Arial, sans-serif;">
                    Violation Management System
                </div>
            </td>
        </tr>

        <!-- Main Content -->
        <tr>
            <td class="main-content" style="padding: 40px 30px; text-align: center;">
                <h1 class="main-heading" style="font-size: 32px; font-weight: bold; color: #800000; margin: 0 0 15px; line-height: 1.2; font-family: Arial, sans-serif;">Verify Your Account</h1>

                <p class="description" style="font-size: 16px; color: #333333; margin: 0 0 30px; line-height: 1.6; font-family: Arial, sans-serif;">You're one step away from accessing PUPTracker. Please enter this one-time passcode to verify your account:</p>

                <div class="otp-code" style="font-size: 48px; font-weight: bold; color: #ffffff; background: linear-gradient(135deg, #800000 0%, #5f0000 100%); margin: 30px 0; padding: 25px; letter-spacing: 8px; font-family: 'Courier New', monospace, Arial; border-radius: 8px; text-align: center;">{{ $otpCode }}</div>

                <p class="expiry-notice" style="font-size: 14px; color: #666666; margin: 30px 0 20px; line-height: 1.6; font-family: Arial, sans-serif; font-style: italic;">This code will expire in {{ $expiryMinutes }} minutes.</p>

                <p class="support-text" style="font-size: 14px; color: #666666; margin: 20px 0 0; line-height: 1.6; font-family: Arial, sans-serif;">If you did not request this verification, please ignore this email or contact our support team immediately at <a href="mailto:support@pup.edu.ph" class="support-link" style="color: #800000; text-decoration: underline; font-weight: bold;">support@pup.edu.ph</a></p>
            </td>
        </tr>

        <!-- Footer -->
        <tr>
            <td class="footer" style="background: linear-gradient(135deg, #5f0000 0%, #800000 100%); padding: 30px 30px; text-align: center;">
                <div class="footer-brand" style="color: #ffdf00; font-size: 12px; font-weight: bold; margin: 0 0 15px; text-transform: uppercase; letter-spacing: 1px; font-family: Arial, sans-serif;">
                    PUP Tracker System
                </div>
                <p class="copyright" style="color: #f5f5f5; font-size: 12px; margin: 0 0 8px; font-family: Arial, sans-serif;">© Philippine University of the Philippines. All Rights Reserved</p>
                <p class="address" style="color: #f5f5f5; font-size: 12px; margin: 0 0 15px; font-family: Arial, sans-serif;">PUP Main Campus, Manila, Philippines</p>
                <p class="footer-links" style="margin: 0;">
                    <a href="mailto:security@pup.edu.ph" style="color: #ffdf00; text-decoration: none; font-size: 12px; font-weight: bold; font-family: Arial, sans-serif;">Security</a>
                    <span class="footer-separator" style="color: #f5f5f5; margin: 0 5px;">|</span>
                    <a href="#" style="color: #ffdf00; text-decoration: none; font-size: 12px; font-weight: bold; font-family: Arial, sans-serif;">Privacy Policy</a>
                </p>
            </td>
        </tr>
    </table>

    <!--[if mso]>
    </td></tr></table>
    <![endif]-->
</body>
</html>
