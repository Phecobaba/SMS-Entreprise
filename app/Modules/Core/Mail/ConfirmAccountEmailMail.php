<?php

namespace App\Modules\Core\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ConfirmAccountEmailMail extends Mailable
{
    public function __construct(public string $recipientName, public string $confirmationUrl) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Confirm your Oakfield email address');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.confirm-account-email');
    }
}
