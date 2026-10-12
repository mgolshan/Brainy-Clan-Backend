<?php

namespace App\Modules\Identity\Models;

use Database\Factories\SocialAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A Google or Facebook account linked to a user (social sign-in, adults only).
 *
 * @property int $id
 * @property int $user_id
 * @property string $provider
 * @property string $provider_user_id
 * @property string|null $email
 * @property Carbon $linked_at
 */
#[Fillable(['user_id', 'provider', 'provider_user_id', 'email', 'linked_at'])]
#[UseFactory(SocialAccountFactory::class)]
class SocialAccount extends Model
{
    /** @use HasFactory<SocialAccountFactory> */
    use HasFactory;

    public const string GOOGLE = 'google';

    public const string FACEBOOK = 'facebook';

    public const array PROVIDERS = [self::GOOGLE, self::FACEBOOK];

    protected function casts(): array
    {
        return [
            'linked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
