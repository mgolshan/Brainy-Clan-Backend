<?php

namespace App\Modules\Support\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Support\Mail\SupportReplyMail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A staff reply to a support message, emailed to the sender ({@see SupportReplyMail}). `body` is
 * plain text written by staff; escaped like the message everywhere.
 *
 * @property int $id
 * @property int $support_message_id
 * @property int|null $staff_user_id
 * @property string $body
 * @property Carbon|null $sent_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read SupportMessage $supportMessage
 * @property-read User|null $staff
 */
#[Fillable(['support_message_id', 'staff_user_id', 'body', 'sent_at'])]
class SupportMessageReply extends Model
{
    /**
     * @var list<string>
     */
    protected $hidden = ['body'];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<SupportMessage, $this>
     */
    public function supportMessage(): BelongsTo
    {
        return $this->belongsTo(SupportMessage::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_user_id');
    }
}
