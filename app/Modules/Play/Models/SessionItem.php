<?php

namespace App\Modules\Play\Models;

use App\Modules\Content\Models\ContentItem;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An item served in a session, with the member's response once answered.
 *
 * @property int $id
 * @property int $play_session_id
 * @property int|null $content_item_id
 * @property string|null $seed
 * @property int $position
 * @property bool $is_redemption
 * @property array<string, mixed>|null $response
 * @property bool|null $is_correct
 * @property int $points_awarded
 * @property int|null $time_ms
 * @property Carbon|null $answered_at
 * @property-read ContentItem|null $contentItem
 * @property-read PlaySession $session
 */
#[Fillable(['play_session_id', 'content_item_id', 'seed', 'position', 'is_redemption', 'response', 'is_correct', 'points_awarded', 'time_ms', 'answered_at'])]
class SessionItem extends Model
{
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_redemption' => 'boolean',
            'response' => 'array',
            'is_correct' => 'boolean',
            'points_awarded' => 'integer',
            'time_ms' => 'integer',
            'answered_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<PlaySession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(PlaySession::class, 'play_session_id');
    }

    /**
     * @return BelongsTo<ContentItem, $this>
     */
    public function contentItem(): BelongsTo
    {
        return $this->belongsTo(ContentItem::class);
    }

    public function isAnswered(): bool
    {
        return $this->answered_at !== null;
    }
}
