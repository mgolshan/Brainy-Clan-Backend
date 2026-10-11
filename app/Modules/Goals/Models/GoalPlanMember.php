<?php

namespace App\Modules\Goals\Models;

use App\Modules\Family\Models\FamilyMember;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A member taking part in a goal plan, optionally with their own target (e.g. smaller for a young child).
 *
 * @property int $id
 * @property int $goal_plan_id
 * @property int $member_id
 * @property int|null $target_override
 * @property-read FamilyMember $member
 */
#[Fillable(['goal_plan_id', 'member_id', 'target_override'])]
class GoalPlanMember extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['target_override' => 'integer'];
    }

    /**
     * @return BelongsTo<FamilyMember, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(FamilyMember::class, 'member_id');
    }
}
