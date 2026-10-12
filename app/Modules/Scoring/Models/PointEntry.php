<?php

namespace App\Modules\Scoring\Models;

use App\Modules\Family\Models\FamilyMember;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * The points ledger. Every point is one row; rows are appended and never updated or deleted.
 * Corrections are new compensating entries (negative amount).
 *
 * @property int $id
 * @property int $family_id
 * @property int $member_id
 * @property int $category_id
 * @property int|null $play_session_id
 * @property int|null $session_item_id
 * @property int $amount
 * @property string $reason
 * @property Carbon $created_at
 */
#[Fillable(['family_id', 'member_id', 'category_id', 'play_session_id', 'session_item_id', 'amount', 'reason', 'created_at'])]
class PointEntry extends Model
{
    public const string REASON_CORRECT_ANSWER = 'correct_answer';

    public const string REASON_CORRECTION = 'correction';

    public const ?string UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Point entries are append-only; add a compensating entry instead.'));
        static::deleting(fn () => throw new LogicException('Point entries are append-only; add a compensating entry instead.'));
    }

    /**
     * @return BelongsTo<FamilyMember, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(FamilyMember::class, 'member_id');
    }
}
