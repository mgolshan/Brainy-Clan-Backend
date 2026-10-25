<?php

namespace App\Modules\Core\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * An operational alert for staff (`OpsAlert::send`), English, in the shared email frame. Sent
 * synchronously, never queued: a failed-job alert must not depend on the queue that failed.
 * The body is plain text without personal data (class names, queues, counts).
 */
class OpsAlertMail extends Mailable
{
    public function __construct(
        public readonly string $alertSubject,
        public readonly string $body,
    ) {
        $this->locale('en');
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->alertSubject);
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.ops-alert', with: [
            'title' => (string) preg_replace('/^\[[^\]]*\]\s*/', '', $this->alertSubject),
            'lines' => explode("\n", $this->body),
        ]);
    }
}
