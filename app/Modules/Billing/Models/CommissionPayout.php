<?php

namespace App\Modules\Billing\Models;

use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A manual monthly payout recorded by staff ("mark paid"): the payable entries of one affiliate,
 * month (`YYYY-MM` of the entries) and currency, and their total.
 *
 * @property int $id
 * @property int $affiliate_id
 * @property string $month
 * @property string $currency
 * @property int $amount_minor
 * @property int $entries_count
 * @property Carbon $paid_at
 * @property int|null $paid_by_user_id
 * @property string|null $note
 * @property-read Affiliate $affiliate
 * @property-read User|null $paidBy
 */
#[Fillable(['affiliate_id', 'month', 'currency', 'amount_minor', 'entries_count', 'paid_at', 'paid_by_user_id', 'note'])]
class CommissionPayout extends Model
{
    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'entries_count' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Affiliate, $this>
     */
    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by_user_id');
    }

    /**
     * @return HasMany<CommissionEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(CommissionEntry::class, 'payout_id');
    }
}
