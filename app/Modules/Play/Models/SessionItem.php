<?php

namespace App\Modules\Play\Models;

use App\Modules\Content\Models\ContentItem;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An item served in a session, with the member's response once answered. Content-backed
 * items point at a content item; generated items (Sudoku, Math Sprint) carry their seed and a
 * payload/answer snapshot instead.
 *
 * @property int|null $content_item_id
 * @property string|null $type_key
 * @property int|null $category_id generated items: the sub-scope that generated it
 * @property int $play_session_id
 * @property int|null $content_item_id
 * @property string|null $seed
 * @property int $position
 * @property bool $is_redemption
 * @property int $points what a correct answer is worth (snapshot)
 * @property array<string, mixed>|null $payload generated items only
 * @property array<string, mixed>|null $answer generated items only
 * @property array<string, mixed>|null $response
 * @property bool|null $is_correct
 * @property int $points_awarded
 * @property int|null $time_ms
 * @property Carbon|null $answered_at
 * @property-read ContentItem|null $contentItem
 * @property-read PlaySession $session
 */
#[Fillable(['play_session_id', 'content_item_id', 'type_key', 'category_id', 'seed', 'position', 'is_redemption', 'points', 'payload', 'answer', 'response', 'is_correct', 'points_awarded', 'time_ms', 'answered_at'])]
class SessionItem extends Model
{
    /** The answer snapshot never leaves the server. */
    protected $hidden = ['answer'];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_redemption' => 'boolean',
            'points' => 'integer',
            'payload' => 'array',
            'answer' => 'array',
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

    /**
     * The item as the challenge type plugins read it: the content item, or for a generated item
     * an unsaved ContentItem built from the snapshot (so every plugin method works the same).
     */
    public function playable(): ?ContentItem
    {
        if ($this->content_item_id !== null) {
            return $this->contentItem;
        }

        if ($this->type_key === null || $this->payload === null) {
            return null;
        }

        return (new ContentItem)->forceFill([
            'category_id' => $this->category_id,
            'type_key' => $this->type_key,
            'payload' => $this->payload,
            'answer' => $this->answer ?? [],
            'source' => 'generated',
            'status' => ContentItem::STATUS_PUBLISHED,
        ]);
    }

    public function typeKey(): ?string
    {
        return $this->type_key ?? ($this->content_item_id !== null ? $this->contentItem?->type_key : null);
    }

    public function isAnswered(): bool
    {
        return $this->answered_at !== null;
    }
}
