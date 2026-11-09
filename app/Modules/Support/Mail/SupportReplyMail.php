<?php

namespace App\Modules\Support\Mail;

use App\Modules\Support\Models\SupportMessage;
use App\Modules\Support\Models\SupportMessageReply;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A staff reply from the Filament "Support inbox", mailed to the sender in the message's language
 * (en/fr) with Reply-To = the support inbox, with the original message quoted below. Both texts
 * are escaped by the `user-text` mail component. Queued; the payload holds ids only.
 */
class SupportReplyMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly SupportMessageReply $reply)
    {
        $this->locale($this->supportMessage()->locale === 'fr' ? 'fr' : 'en');
    }

    public function envelope(): Envelope
    {
        $message = $this->supportMessage();
        $fr = $message->locale === 'fr';

        return new Envelope(
            subject: $fr
                ? 'Re : votre message à l’assistance Brainy Clan (no '.$message->id.')'
                : 'Re: your message to Brainy Clan support (#'.$message->id.')',
            replyTo: [new Address((string) config('legal.support_email'), $fr ? 'Assistance Brainy Clan' : 'Brainy Clan support')],
        );
    }

    public function content(): Content
    {
        $message = $this->supportMessage();
        $locale = $message->locale === 'fr' ? 'fr' : 'en';

        return new Content(markdown: 'mail.support-reply', with: [
            'locale' => $locale,
            'replyBody' => $this->reply->body,
            'original' => $message->message,
            'sentOn' => ($message->created_at ?? now())->locale($locale)->isoFormat('LL'),
        ]);
    }

    private function supportMessage(): SupportMessage
    {
        return $this->reply->loadMissing('supportMessage')->supportMessage;
    }
}
