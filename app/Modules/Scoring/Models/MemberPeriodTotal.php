<?php

namespace App\Modules\Scoring\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Cached points per member and goal period, rebuilt from the ledger (never the source of truth).
 *
 * @property int $id
 * @property int $member_id
 * @property int $goal_period_id
 * @property int $points
 * @property int $sessions
 */
#[Fillable(['member_id', 'goal_period_id', 'points', 'sessions'])]
class MemberPeriodTotal extends Model
{
    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'sessions' => 'integer',
        ];
    }
}
