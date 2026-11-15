<?php

namespace App\Modules\Core\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class PasswordResetLinkMail extends Mailable
{
    public function __construct(public string $recipientName, public string $resetUrl, public int $expiresMinutes) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Reset your Oakfield password');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.password-reset-link');
    }
}
