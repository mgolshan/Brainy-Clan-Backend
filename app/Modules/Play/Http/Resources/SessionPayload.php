<?php

namespace App\Modules\Play\Http\Resources;

use App\Modules\Play\Models\PlaySession;
use App\Modules\Play\Models\SessionItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * `{ session, items }` — always every item of the session (answered ones included), by position.
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
            ->orderBy('position')
            ->get();

        return [
            'session' => (new SessionResource($this->resource))->resolve($request),
            'items' => SessionItemResource::collection($items)->resolve($request),
        ];
    }
}
