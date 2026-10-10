<?php

namespace App\Modules\Family\Models;

use App\Modules\Identity\Models\User;
use Database\Factories\InviteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A single-use code that lets a new member (usually a child) join a family on their device.
 *
 * @property int $id
 * @property int $family_id
 * @property string $code
 * @property int $created_by
 * @property string $nickname
 * @property string|null $avatar
 * @property int $birth_year
 * @property int $birth_month
 * @property Carbon $expires_at
 * @property int|null $used_by
 * @property Carbon|null $used_at
 * @property Carbon|null $created_at
 * @property-read Family $family
 */
#[Fillable(['family_id', 'code', 'created_by', 'nickname', 'avatar', 'birth_year', 'birth_month', 'expires_at', 'used_by', 'used_at'])]
#[UseFactory(InviteFactory::class)]
class Invite extends Model
{
    /** @use HasFactory<InviteFactory> */
    use HasFactory;

    /** No 0/O/1/I so codes are easy to read aloud and type. */
    public const string CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public const int CODE_LENGTH = 8;

    protected function casts(): array
    {
        return [
            'birth_year' => 'integer',
            'birth_month' => 'integer',
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Family, $this>
     */
    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function usedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'used_by');
    }

    public function isPending(): bool
    {
        return $this->used_at === null && $this->expires_at->isFuture();
    }

    /**
     * @param  Builder<Invite>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->whereNull('used_at')->where('expires_at', '>', now());
    }

    public static function normalizeCode(string $code): string
    {
        return strtoupper(preg_replace('/[\s-]+/', '', $code) ?? '');
    }
}
