<?php

namespace App\Mail;

use App\Models\ReferenceRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReferenceCompletedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ReferenceRequest $reference,
        public string $applicantName,
        public ?string $reviewUrl = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Reference Completed — {$this->reference->reference_name} for {$this->applicantName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reference-completed',
        );
    }
}
