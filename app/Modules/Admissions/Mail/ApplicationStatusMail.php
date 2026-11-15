<?php

namespace App\Modules\Admissions\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ApplicationStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly string $kind, public readonly string $reference, public readonly string $statusUrl) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->kind === 'registered' ? 'Application registered' : 'Complete your application payment');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.application-status');
    }
}
