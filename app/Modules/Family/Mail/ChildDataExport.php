<?php

namespace App\Modules\Family\Mail;

use App\Modules\Identity\Actions\ExportUserData;
use App\Modules\Identity\Models\User;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * A child's data export (same JSON as `GET /me/export`) mailed to the family owner after a
 * verified parent request (staff action "Start data export"). Sent synchronously, not queued,
 * so the export never sits in a Redis job payload or a failed-job row.
 */
class ChildDataExport extends Mailable
{
    public function __construct(
        public readonly User $child,
        public readonly string $nickname,
        public readonly string $mailLocale,
    ) {
        $this->locale($mailLocale);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->mailLocale === 'fr' ? 'Copie des données Brainy Clan de '.$this->nickname : 'Brainy Clan data copy for '.$this->nickname,
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.child-data-export', with: [
            'locale' => $this->mailLocale,
            'nickname' => $this->nickname,
            'supportEmail' => (string) config('legal.support_email'),
        ]);
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $json = (string) json_encode(
            ['data' => app(ExportUserData::class)->build($this->child)],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );

        return [
            Attachment::fromData(fn (): string => $json, 'brainy-clan-export-'.now()->format('Y-m-d').'.json')->withMime('application/json'),
        ];
    }
}
