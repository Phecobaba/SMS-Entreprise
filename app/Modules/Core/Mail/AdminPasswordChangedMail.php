<?php

namespace App\Modules\Core\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class AdminPasswordChangedMail extends Mailable
{
    public function __construct(public string $recipientName, public string $accountName) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'An Oakfield Admin account password was reset');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.admin-password-changed');
    }
}
