<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactFormSubmission extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public string $senderName;

    public string $senderEmail;

    public string $contactSubject;

    public string $messageContent;

    public function __construct(
        string $senderName,
        string $senderEmail,
        string $subject,
        string $messageContent,
    ) {
        $this->senderName = $senderName;
        $this->senderEmail = $senderEmail;
        $this->contactSubject = $subject;
        $this->messageContent = $messageContent;
        $this->onQueue('mail');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: [
                new Address($this->senderEmail, $this->senderName),
            ],
            subject: 'Contact Form: ' . $this->contactSubject,
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'emails.contact-form-text',
        );
    }
}
