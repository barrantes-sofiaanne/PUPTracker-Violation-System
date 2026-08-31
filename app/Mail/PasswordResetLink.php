<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordResetLink extends Mailable
{
    use Queueable, SerializesModels;

    public string $resetUrl;
    public int $expiryMinutes;

    public function __construct(string $resetUrl, int $expiryMinutes = 15)
    {
        $this->resetUrl = $resetUrl;
        $this->expiryMinutes = $expiryMinutes;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Reset Your PUPTracker Password',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.password-reset-link',
            with: [
                'resetUrl' => $this->resetUrl,
                'expiryMinutes' => $this->expiryMinutes,
            ],
        );
    }
}
