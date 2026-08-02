<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OtpCode extends Mailable
{
    use Queueable, SerializesModels;

    public string $otpCode;
    public int $expiryMinutes;
    public string $recipientName;

    public function __construct(string $otpCode, int $expiryMinutes = 10, string $recipientName = 'User')
    {
        $this->otpCode = $otpCode;
        $this->expiryMinutes = $expiryMinutes;
        $this->recipientName = $recipientName;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Verify Your PUPTracker Account - One-Time Passcode',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.otp',
            with: [
                'otpCode' => $this->otpCode,
                'expiryMinutes' => $this->expiryMinutes,
                'recipientName' => $this->recipientName,
            ],
        );
    }
}
