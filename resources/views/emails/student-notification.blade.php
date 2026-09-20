<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $notificationTitle }}</title>
</head>
<body style="margin:0; padding:0; background:#f4f6f8; font-family:Arial, sans-serif;">

    <div style="max-width:600px; margin:40px auto; background:#ffffff; border-radius:8px; overflow:hidden;">

        <div style="padding:24px; background:#800000; color:#ffffff;">
            <h2 style="margin:0;">PUPTracker</h2>
        </div>

        <div style="padding:30px;">
            <p style="font-size:16px;">
                Hello {{ $recipientName }},
            </p>

            <h2 style="color:#333333;">
                {{ $notificationTitle }}
            </h2>

            <p style="font-size:15px; line-height:1.6; color:#555555;">
                {{ $notificationMessage }}
            </p>

            @if ($actionUrl)
                <div style="margin-top:25px;">
                    <a href="{{ $actionUrl }}"
                       style="display:inline-block; padding:12px 20px; background:#800000; color:#ffffff; text-decoration:none; border-radius:5px;">
                        {{ $actionText }}
                    </a>
                </div>
            @endif

            <p style="margin-top:30px; font-size:13px; color:#777777;">
                This is an automated notification from PUPTracker. Please do not reply to this email.
            </p>
        </div>

    </div>

</body>
</html>