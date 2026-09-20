<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PUPTracker Notification</title>

    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
            font-family: Arial, sans-serif;
            line-height: 1.6;
        }

        .container {
            max-width: 600px;
            margin: 30px auto;
            background: #ffffff;
        }

        .header {
            background: #800000;
            padding: 30px;
            text-align: center;
        }

        .brand {
            color: #ffdf00;
            font-size: 26px;
            font-weight: bold;
        }

        .subtitle {
            color: #ffffff;
            font-size: 13px;
            margin-top: 5px;
        }

        .content {
            padding: 35px 30px;
        }

        .title {
            color: #800000;
            font-size: 24px;
            margin-top: 0;
        }

        .message {
            color: #333333;
            font-size: 15px;
            white-space: pre-line;
        }

        .button {
            display: inline-block;
            margin-top: 25px;
            padding: 13px 25px;
            background: #800000;
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 6px;
            font-weight: bold;
        }

        .footer {
            background: #5f0000;
            padding: 25px;
            text-align: center;
            color: #ffffff;
            font-size: 12px;
        }
    </style>
</head>

<body>

<div class="container">

    <div class="header">
        <div class="brand">PUPTracker</div>
        <div class="subtitle">Violation Tracking System</div>
    </div>

    <div class="content">

        <h1 class="title">
            {{ $notificationTitle }}
        </h1>

        <p>
            Hello {{ $recipientName }},
        </p>

        <p class="message">
            {{ $notificationMessage }}
        </p>

        @if($actionUrl)
            <p>
                <a href="{{ $actionUrl }}" class="button">
                    {{ $actionText }}
                </a>
            </p>
        @endif

        <p>
            Please log in to PUPTracker to review this notification.
        </p>

    </div>

    <div class="footer">
        <strong>PUPTracker Violation System</strong>
        <br>
        Polytechnic University of the Philippines - Taguig Campus
        <br><br>
        This is an automated notification. Please do not reply to this email.
    </div>

</div>

</body>
</html>