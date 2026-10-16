<?php

namespace App\Modules\Billing\Models;

use App\Modules\Family\Models\FamilyMember;
use App\Modules\Play\Models\PlaySession;
use Database\Factories\AdRewardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A rewarded ad confirmed by AdMob server-side verification; single use for a second chance.
 *
 * @property int $id
 * @property string $transaction_id
 * @property string $reward_ref
 * @property int $member_id
 * @property int $play_session_id
 * @property string|null $ad_unit
 * @property string|null $reward_item
 * @property int|null $reward_amount
 * @property array<string, mixed> $payload
 * @property Carbon|null $used_at
 * @property Carbon $created_at
 * @property-read FamilyMember $member
 * @property-read PlaySession $session
 */
#[Fillable(['transaction_id', 'reward_ref', 'member_id', 'play_session_id', 'ad_unit', 'reward_item', 'reward_amount', 'payload', 'used_at'])]
#[UseFactory(AdRewardFactory::class)]
class AdReward extends Model
{
    /** @use HasFactory<AdRewardFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'reward_amount' => 'integer',
            'used_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<FamilyMember, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(FamilyMember::class);
    }

    /**
     * @return BelongsTo<PlaySession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(PlaySession::class, 'play_session_id');
    }
}
