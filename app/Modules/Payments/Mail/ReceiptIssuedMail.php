<?php

namespace App\Modules\Payments\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReceiptIssuedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $receiptNumber,
        public readonly string $pdfBytes,
        public readonly ?string $statusUrl = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Payment receipt '.$this->receiptNumber);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.receipt-issued');
    }

    public function attachments(): array
    {
        return [Attachment::fromData(fn (): string => $this->pdfBytes, $this->receiptNumber.'.pdf')->withMime('application/pdf')];
    }
}
