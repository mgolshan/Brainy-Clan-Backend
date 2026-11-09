<?php

namespace App\Modules\Support\Actions;

use App\Modules\Identity\Models\User;
use App\Modules\Support\Mail\SupportReplyMail;
use App\Modules\Support\Models\SupportMessage;
use App\Modules\Support\Models\SupportMessageReply;
use App\Modules\Support\Support\SupportText;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;

/**
 * Staff reply from the Filament "Support inbox" (admin and support roles): stores the reply,
 * mails it (queued, branded template, in the sender's language) to the sender — the account's
 * current email for app messages, the typed address for website messages — with Reply-To = the
 * support inbox, and marks the message `answered` (`answered_at`, `last_activity_at`). A closed
 * message that gets a reply is answered again.
 */
class ReplyToSupportMessage
{
    public function handle(SupportMessage $message, User $staff, string $body): SupportMessageReply
    {
        $text = SupportText::normalize($body);

        if ($text === null || $text === '' || mb_strlen($text) > SupportText::REPLY_MAX_LENGTH) {
            throw new InvalidArgumentException('The reply must be between 1 and '.SupportText::REPLY_MAX_LENGTH.' characters.');
        }

        $message->loadMissing('user');
        $to = $message->replyEmail();

        if ($to === null || $to === '') {
            throw new InvalidArgumentException('This message has no email address to reply to.');
        }

        $reply = DB::transaction(function () use ($message, $staff, $text): SupportMessageReply {
            $now = now();

            $reply = $message->replies()->create([
                'staff_user_id' => $staff->id,
                'body' => $text,
                'sent_at' => $now,
            ]);

            $message->forceFill([
                'status' => SupportMessage::STATUS_ANSWERED,
                'answered_at' => $now,
                'closed_at' => null,
                'last_activity_at' => $now,
            ])->save();

            return $reply;
        });

        Mail::to($to)->queue(new SupportReplyMail($reply));

        return $reply;
    }
}
