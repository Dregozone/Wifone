<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent instead of one email per sign-up while sign-ups arrive in a wave.
 */
class SignUpBurst extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public int $recentSignUps,
        public int $windowMinutes,
        public int $totalUsers,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "{$this->recentSignUps} Wifone sign-ups in the last {$this->windowMinutes} minutes",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.sign-up-burst',
        );
    }
}
