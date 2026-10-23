<?php

namespace App\Modules\Identity\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Notice after a password reset on `/password/reset`: the password changed and every device was
 * signed out. Queued.
 */
class PasswordChanged extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly string $mailLocale) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->mailLocale === 'fr' ? 'Votre mot de passe Brainy Clan a été modifié' : 'Your Brainy Clan password was changed',
        );
    }

    public function content(): Content
    {
        return new Content(text: 'password.changed-mail', with: [
            'locale' => $this->mailLocale,
            'supportEmail' => (string) config('legal.support_email'),
        ]);
    }
}
