<?php

namespace App\Modules\Identity\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * "Someone tried to sign in": sent when the per-email sign-in lock starts (many wrong passwords
 * from any number of devices), at most once per 24 hours per account. Queued.
 */
class SignInAttemptsNotice extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $mailLocale,
        public readonly int $lockMinutes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->mailLocale === 'fr' ? 'Tentatives de connexion à votre compte Brainy Clan' : 'Sign-in attempts on your Brainy Clan account',
        );
    }

    public function content(): Content
    {
        return new Content(text: 'password.sign-in-attempts-mail', with: [
            'locale' => $this->mailLocale,
            'minutes' => $this->lockMinutes,
            'supportEmail' => (string) config('legal.support_email'),
        ]);
    }
}
