<?php

namespace App\Modules\Play\Http\Resources;

use App\Modules\Play\Models\PlaySession;
use App\Modules\Play\Models\SessionItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * `{ session, items }` — every item of the session (answered ones included), by position.
 * Unanswered redemption items are void outside a running redemption round (the round failed
 * on a wrong answer), so they are left out.
 *
 * @property PlaySession $resource
 */
class SessionPayload extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $items = SessionItem::query()
            ->with('contentItem')
            ->where('play_session_id', $this->resource->id)
            ->when($this->resource->status !== PlaySession::STATUS_REDEMPTION, fn ($q) => $q->where(
                fn ($q) => $q->where('is_redemption', false)->orWhereNotNull('answered_at'),
            ))
            ->orderBy('position')
            ->get();

        return [
            'session' => (new SessionResource($this->resource))->resolve($request),
            'items' => SessionItemResource::collection($items)->resolve($request),
        ];
    }
}
