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

@if (($notificationTitle ?? '') === 'Sanction Request Approved')
    @php
        $message = (string) ($notificationMessage ?? '');

        $intro = 'Your sanction request has been approved. Please review your Sanction Record tab for the details of your assigned sanction.';

        $dateAssigned = null;
        $designatedOffice = null;
        $startingDate = null;
        $deadlineDate = null;

        if (preg_match('/Date Assigned:\s*(.*?)(?=\.\s*(?:Designated Office|Designated office|Deadline to Complete|$)|$)/i', $message, $matches)) {
            $dateAssigned = trim($matches[1]);
        }

        if (preg_match('/Designated Office:\s*(.*?)(?=\.\s*(?:Starting Date|Starting date|Deadline to Complete|$)|$)/i', $message, $matches)) {
            $designatedOffice = trim($matches[1]);
        }

        if (preg_match('/Starting Date:\s*(.*?)(?=\.\s*(?:Deadline to Complete|$)|$)/i', $message, $matches)) {
            $startingDate = trim($matches[1]);
        }

        if (preg_match('/Deadline to Complete:\s*(.*?)(?=\.$|$)/i', $message, $matches)) {
            $deadlineDate = trim($matches[1]);
        }
    @endphp

    <p style="font-size: 16px; line-height: 1.7; color: #555555;">
        {{ $intro }}
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
           border="0" style="border-collapse: collapse; margin: 20px 0;">
        @foreach ([
            'Date Assigned' => $dateAssigned,
            'Designated Office' => $designatedOffice,
            'Starting Date' => $startingDate,
            'Deadline to Complete' => $deadlineDate,
        ] as $label => $value)
            @if (filled($value))
                <tr>
                    <td style="padding: 12px; border-bottom: 1px solid #eeeeee; font-weight: bold; color: #333333; vertical-align: top;">
                        {{ $label }}
                    </td>
                    <td style="padding: 12px; border-bottom: 1px solid #eeeeee; color: #555555; vertical-align: top;">
                        {{ rtrim($value, '.') }}
                    </td>
                </tr>
            @endif
        @endforeach
    </table>

    <p style="font-size: 14px; line-height: 1.6; color: #666666;">
        Please observe your starting date and complete the assigned sanction by the deadline.
    </p>
@else
    <p>{{ $notificationMessage }}</p>
@endif

            <p style="margin-top:30px; font-size:13px; color:#777777;">
                This is an automated notification from PUPTracker. Please do not reply to this email.
            </p>
        </div>

    </div>

</body>
</html>