<?php

namespace App\Modules\Goals\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One window of a goal plan, [starts_at, ends_at) in UTC. Targets are a snapshot taken when the
 * period opened; `result` (GoalProgress + winner_member_ids) is written when it closes.
 *
 * @property int $id
 * @property int $goal_plan_id
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property string $status
 * @property int $target_points
 * @property array<int|string, int> $member_targets
 * @property array<string, mixed>|null $result
 * @property Carbon|null $closed_at
 * @property-read GoalPlan $plan
 */
#[Fillable(['goal_plan_id', 'starts_at', 'ends_at', 'status', 'target_points', 'member_targets', 'result', 'closed_at'])]
class GoalPeriod extends Model
{
    public const string STATUS_OPEN = 'open';

    public const string STATUS_CLOSED = 'closed';

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'target_points' => 'integer',
            'member_targets' => 'array',
            'result' => 'array',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<GoalPlan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(GoalPlan::class, 'goal_plan_id');
    }
}
