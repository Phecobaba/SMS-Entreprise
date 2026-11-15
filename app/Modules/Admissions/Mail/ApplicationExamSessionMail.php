<?php

namespace App\Modules\Admissions\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ApplicationExamSessionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $childFirstName,
        public readonly string $sessionName,
        public readonly string $sessionDate,
        public readonly string $sessionTime,
        public readonly string $venue,
        public readonly string $statusUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your entrance exam session');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.application-exam-session');
    }
}
