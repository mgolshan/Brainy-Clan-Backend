<?php

namespace App\Modules\Family\Models;

use App\Modules\Identity\Models\Device;
use App\Modules\Identity\Models\User;
use Database\Factories\SignInCodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A one-time code an admin issues so a member can sign in on a new device (no email needed).
 * Valid for `sign_in_codes.ttl_minutes` (15), single use; a newer code voids older unused ones.
 * Only an HMAC of the normalized code is stored (`code_hash`), never the code itself.
 *
 * @property int $id
 * @property int $member_id
 * @property string $code_hash
 * @property int|null $created_by
 * @property Carbon $expires_at
 * @property Carbon|null $used_at
 * @property int|null $device_id
 * @property Carbon|null $voided_at
 * @property Carbon|null $created_at
 * @property-read FamilyMember $member
 */
#[Fillable(['member_id', 'code_hash', 'created_by', 'expires_at', 'used_at', 'device_id', 'voided_at'])]
#[UseFactory(SignInCodeFactory::class)]
class SignInCode extends Model
{
    /** @use HasFactory<SignInCodeFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    /**
     * Codes are typed by people: case-insensitive, dashes and spaces ignored (same as invites).
     */
    public static function hashCode(string $code): string
    {
        return hash_hmac('sha256', Invite::normalizeCode($code), (string) config('app.key'));
    }

    /**
     * @return BelongsTo<FamilyMember, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(FamilyMember::class, 'member_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<Device, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function isUsable(): bool
    {
        return $this->used_at === null && $this->voided_at === null && $this->expires_at->isFuture();
    }

    /**
     * Not used, not replaced, not expired.
     *
     * @param  Builder<SignInCode>  $query
     */
    public function scopeUsable(Builder $query): void
    {
        $query->whereNull('used_at')->whereNull('voided_at')->where('expires_at', '>', now());
    }
}
