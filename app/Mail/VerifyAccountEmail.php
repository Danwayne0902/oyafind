<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VerifyAccountEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $verificationUrl,
        public readonly int $expirationMinutes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Verify your OyaFind email address');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.verify-account');
    }
}
