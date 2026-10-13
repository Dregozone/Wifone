<?php

namespace App\Mail;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells the admin (ADMIN_NOTIFY_EMAIL) that someone has signed up.
 */
class NewUserSignedUp extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, string>  $source  Where they came from, e.g. ['referrer' => '...', 'utm_source' => '...'].
     */
    public function __construct(
        public User $user,
        public CarbonImmutable $signedUpAt,
        public int $totalUsers,
        public ?string $country = null,
        public array $source = [],
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "New Wifone sign-up: {$this->user->name}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.new-user-signed-up',
        );
    }
}
