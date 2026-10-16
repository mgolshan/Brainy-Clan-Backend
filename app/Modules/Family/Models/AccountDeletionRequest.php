<?php

namespace App\Modules\Family\Models;

use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A deletion request made on the public page `/account/delete` (docs/API.md "Account deletion web
 * page"). Holds no plain email: `email_hash` (HMAC) and `token_hash` (SHA-256 of the emailed link).
 *
 * @property int $id
 * @property string $email_hash
 * @property int|null $user_id
 * @property string|null $token_hash
 * @property string $status
 * @property Carbon $expires_at
 * @property Carbon|null $confirmed_at
 * @property string|null $ip_hash
 * @property Carbon|null $created_at
 * @property-read User|null $user
 */
#[Fillable(['email_hash', 'user_id', 'token_hash', 'status', 'expires_at', 'confirmed_at', 'ip_hash'])]
class AccountDeletionRequest extends Model
{
    public const string STATUS_PENDING = 'pending';

    public const string STATUS_COMPLETED = 'completed';

    public const string STATUS_BLOCKED = 'blocked';

    public const string STATUS_SUPERSEDED = 'superseded';

    public const string STATUS_NO_ACCOUNT = 'no_account';

    public const int TTL_MINUTES = 60;

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isUsable(): bool
    {
        return $this->status === self::STATUS_PENDING && $this->user_id !== null && $this->expires_at->isFuture();
    }

    public static function hashEmail(string $email): string
    {
        return hash_hmac('sha256', strtolower(trim($email)), (string) config('app.key'));
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
