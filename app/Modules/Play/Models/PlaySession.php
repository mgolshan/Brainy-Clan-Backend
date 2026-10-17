<?php

namespace App\Modules\Play\Models;

use App\Modules\Content\Models\Category;
use App\Modules\Family\Models\FamilyMember;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One sitting: a target, a mistake allowance (blunder cap) and the items served.
 *
 * @property int $id
 * @property int $family_id
 * @property int $member_id
 * @property int $category_id
 * @property int $target_points
 * @property int $blunder_cap
 * @property int $mistakes
 * @property int $points
 * @property int $second_chances_used
 * @property bool $redemption_failed
 * @property string $status
 * @property Carbon $started_at
 * @property Carbon|null $completed_at
 * @property-read FamilyMember $member
 */
#[Fillable(['family_id', 'member_id', 'category_id', 'target_points', 'blunder_cap', 'mistakes', 'points', 'second_chances_used', 'redemption_failed', 'status', 'started_at', 'completed_at'])]
class PlaySession extends Model
{
    public const string STATUS_ACTIVE = 'active';

    public const string STATUS_REDEMPTION = 'redemption';

    public const string STATUS_FAILED = 'failed';

    public const string STATUS_COMPLETED = 'completed';

    public const string STATUS_ABANDONED = 'abandoned';

    /** Resumable: the app offers "Continue your game" (`GET /play/sessions/current`). */
    public const array UNFINISHED_STATUSES = [self::STATUS_ACTIVE, self::STATUS_FAILED, self::STATUS_REDEMPTION];

    protected function casts(): array
    {
        return [
            'target_points' => 'integer',
            'blunder_cap' => 'integer',
            'mistakes' => 'integer',
            'points' => 'integer',
            'second_chances_used' => 'integer',
            'redemption_failed' => 'boolean',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<FamilyMember, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(FamilyMember::class, 'member_id');
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany<SessionItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SessionItem::class)->orderBy('position');
    }

    /**
     * @return HasMany<SecondChance, $this>
     */
    public function secondChances(): HasMany
    {
        return $this->hasMany(SecondChance::class);
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_ABANDONED], true);
    }
}
