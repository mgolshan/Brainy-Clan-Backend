<?php

namespace App\Modules\Scoring\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Consecutive active days (family timezone) per member.
 *
 * @property int $id
 * @property int $member_id
 * @property int $current
 * @property int $longest
 * @property Carbon|null $last_active_date
 */
#[Fillable(['member_id', 'current', 'longest', 'last_active_date'])]
class Streak extends Model
{
    protected function casts(): array
    {
        return [
            'current' => 'integer',
            'longest' => 'integer',
            'last_active_date' => 'date',
        ];
    }
}
