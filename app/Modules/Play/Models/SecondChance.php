<?php

namespace App\Modules\Play\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $play_session_id
 * @property int $member_id
 * @property string $method
 * @property string|null $reward_ref
 * @property Carbon $granted_at
 */
#[Fillable(['play_session_id', 'member_id', 'method', 'reward_ref', 'granted_at'])]
class SecondChance extends Model
{
    public const string METHOD_FREE = 'free';

    public const string METHOD_AD = 'ad';

    public const string METHOD_REDEMPTION = 'redemption';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'granted_at' => 'datetime',
        ];
    }
}
