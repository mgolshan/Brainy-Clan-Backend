<?php

namespace App\Modules\Identity\Models;

use Database\Factories\SocialAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A Google, Facebook or Apple account linked to a user (social sign-in, adults only).
 *
 * @property int $id
 * @property int $user_id
 * @property string $provider
 * @property string $provider_user_id
 * @property string|null $email
 * @property string|null $refresh_token Apple only (encrypted): revoked on unlink and account deletion
 * @property string|null $token_client_id The Apple client id the refresh token was issued for
 * @property Carbon $linked_at
 */
#[Fillable(['user_id', 'provider', 'provider_user_id', 'email', 'linked_at'])]
#[Hidden(['refresh_token'])]
#[UseFactory(SocialAccountFactory::class)]
class SocialAccount extends Model
{
    /** @use HasFactory<SocialAccountFactory> */
    use HasFactory;

    public const string GOOGLE = 'google';

    public const string FACEBOOK = 'facebook';

    public const string APPLE = 'apple';

    public const array PROVIDERS = [self::GOOGLE, self::FACEBOOK, self::APPLE];

    protected function casts(): array
    {
        return [
            'linked_at' => 'datetime',
            'refresh_token' => 'encrypted',
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
