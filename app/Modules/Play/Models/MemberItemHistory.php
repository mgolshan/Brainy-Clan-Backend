<?php

namespace App\Modules\Play\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * How often a member has seen / answered an item: avoids repeats, enables spaced review later.
 *
 * @property int $id
 * @property int $member_id
 * @property int $content_item_id
 * @property int $times_seen
 * @property int $times_correct
 * @property Carbon|null $last_seen_at
 */
#[Fillable(['member_id', 'content_item_id', 'times_seen', 'times_correct', 'last_seen_at'])]
class MemberItemHistory extends Model
{
    protected $table = 'member_item_history';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'times_seen' => 'integer',
            'times_correct' => 'integer',
            'last_seen_at' => 'datetime',
        ];
    }
}
