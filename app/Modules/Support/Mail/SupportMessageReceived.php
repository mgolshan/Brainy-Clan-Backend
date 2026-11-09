<?php

namespace App\Modules\Support\Mail;

use App\Modules\Support\Models\SupportMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A new support message (app or website form), mailed to the support inbox
 * (`config('legal.support_email')`) in English, with Reply-To = the sender, so staff can also
 * answer from their mail client. The subject holds no user text (no header injection, no spoofed
 * subjects); the sender's name and text are escaped by the `details` / `user-text` mail
 * components. Queued; only the message id is in the job payload (SerializesModels), the text is
 * read from the database when the mail is built.
 */
class SupportMessageReceived extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly SupportMessage $supportMessage)
    {
        $this->locale('en');
    }

    public function envelope(): Envelope
    {
        $message = $this->supportMessage->loadMissing(['user', 'family']);
        $replyTo = $message->replyEmail();

        return new Envelope(
            subject: 'Support message #'.$message->id.': '.SupportMessage::topicLabel($message->topic).' ('.($message->source === SupportMessage::SOURCE_APP ? 'app' : 'website').')',
            replyTo: $replyTo !== null && $replyTo !== '' ? [new Address($replyTo, (string) $message->name)] : [],
        );
    }

    public function content(): Content
    {
        $message = $this->supportMessage->loadMissing(['user', 'family']);

        return new Content(markdown: 'mail.support-message-received', with: [
            'id' => $message->id,
            'details' => self::details($message),
            'body' => $message->message,
            'inboxUrl' => url('/admin/support-messages/'.$message->id),
        ]);
    }

    /**
     * Label => value rows describing the message (values may be text people typed).
     *
     * @return array<string, string>
     */
    public static function details(SupportMessage $message): array
    {
        $rows = [
            'From' => trim(($message->name ?? '').' <'.($message->replyEmail() ?? '—').'>'),
            'Topic' => SupportMessage::topicLabel($message->topic),
            'Sent from' => $message->source === SupportMessage::SOURCE_APP ? 'The app (family admin)' : 'The website contact form',
            'Language' => $message->locale === 'fr' ? 'French' : 'English',
        ];

        if ($message->source === SupportMessage::SOURCE_APP) {
            $rows['Family'] = $message->family !== null ? '#'.$message->family->id.' ('.$message->family->tier.' plan)' : '—';
            $rows['App'] = trim(implode(' · ', array_filter([$message->app_version, $message->platform, $message->device])));
        }

        $rows['Received'] = ($message->created_at ?? now())->utc()->format('Y-m-d H:i').' UTC';

        return $rows;
    }
}
