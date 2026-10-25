<?php

namespace App\Modules\Billing\Mail;

use App\Modules\Billing\Actions\RequestWebCheckout;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The answer to the website subscribe form: the one-time checkout link (`$checkoutUrl`), or why
 * this address can't subscribe on the web (`$reason`, a `CheckWebCheckoutEligibility` constant).
 * Queued, so the form answers equally fast in every case.
 */
class WebCheckoutMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $pageLocale,
        public readonly string $plan,
        public readonly ?string $checkoutUrl,
        public readonly ?string $reason,
        public readonly ?string $store,
    ) {
        $this->locale($pageLocale);
    }

    public function envelope(): Envelope
    {
        $fr = $this->pageLocale === 'fr';

        return new Envelope(
            subject: $this->checkoutUrl !== null
                ? ($fr ? 'Votre lien pour vous abonner à Brainy Clan' : 'Your link to subscribe to Brainy Clan')
                : ($fr ? 'Votre demande d’abonnement Brainy Clan' : 'Your Brainy Clan subscription request'),
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.web-checkout', with: [
            'locale' => $this->pageLocale,
            'checkoutUrl' => $this->checkoutUrl,
            'reason' => $this->reason,
            'storeLabel' => RequestWebCheckout::storeLabel($this->store, $this->pageLocale),
            'minutes' => (int) config('billing.web_checkout.link_ttl_minutes', 30),
            'supportEmail' => (string) config('legal.support_email'),
        ]);
    }
}
