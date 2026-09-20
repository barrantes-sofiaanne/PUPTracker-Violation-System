<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SystemNotification extends Mailable
{
    use Queueable, SerializesModels;

    public string $notificationTitle;
    public string $notificationMessage;
    public string $recipientName;
    public ?string $actionUrl;
    public ?string $actionText;

    public function __construct(
        string $notificationTitle,
        string $notificationMessage,
        string $recipientName = 'User',
        ?string $actionUrl = null,
        ?string $actionText = 'View Notification'
    ) {
        $this->notificationTitle = $notificationTitle;
        $this->notificationMessage = $notificationMessage;
        $this->recipientName = $recipientName;
        $this->actionUrl = $actionUrl;
        $this->actionText = $actionText;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'PUPTracker - ' . $this->notificationTitle,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.system-notification',
            with: [
                'notificationTitle' => $this->notificationTitle,
                'notificationMessage' => $this->notificationMessage,
                'recipientName' => $this->recipientName,
                'actionUrl' => $this->actionUrl,
                'actionText' => $this->actionText,
            ],
        );
    }
}