<?php

namespace App\Modules\Identity\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The one-time link to `/password/reset` (`POST /auth/password/forgot`). Queued, so the API
 * answers equally fast whether or not the email has an account.
 */
class PasswordResetLink extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $resetUrl,
        public readonly string $mailLocale,
        public readonly int $minutes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->mailLocale === 'fr' ? 'Réinitialisez votre mot de passe Brainy Clan' : 'Reset your Brainy Clan password',
        );
    }

    public function content(): Content
    {
        return new Content(text: 'password.reset-link-mail', with: [
            'resetUrl' => $this->resetUrl,
            'locale' => $this->mailLocale,
            'minutes' => $this->minutes,
            'supportEmail' => (string) config('legal.support_email'),
        ]);
    }
}
