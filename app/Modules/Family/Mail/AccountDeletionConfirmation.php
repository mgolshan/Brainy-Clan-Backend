<?php

namespace App\Modules\Family\Mail;

use App\Modules\Family\Models\AccountDeletionRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The one-time link that confirms an account deletion requested on `/account/delete`.
 * Queued, so the page answers equally fast whether or not the email belongs to an account.
 */
class AccountDeletionConfirmation extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $confirmUrl,
        public readonly string $pageLocale,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->pageLocale === 'fr' ? 'Confirmez la suppression de votre compte Brainy Clan' : 'Confirm the deletion of your Brainy Clan account',
        );
    }

    public function content(): Content
    {
        return new Content(text: 'account.confirmation-mail', with: [
            'confirmUrl' => $this->confirmUrl,
            'locale' => $this->pageLocale,
            'minutes' => AccountDeletionRequest::TTL_MINUTES,
        ]);
    }
}
