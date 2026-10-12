<?php

namespace App\Modules\Play\Http\Resources;

use App\Modules\ChallengeTypes\ChallengeTypeRegistry;
use App\Modules\Play\Models\SessionItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A served item with its answer stripped (the plugin's forClient()). Content-backed and
 * generated items look the same; `points` is what a correct answer is worth.
 *
 * @property SessionItem $resource
 */
class SessionItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $item = $this->resource;
        $playable = $item->playable();
        $type = $item->typeKey();
        $registry = app(ChallengeTypeRegistry::class);

        return [
            'id' => $item->id,
            'position' => $item->position,
            'type' => $type ?? 'unknown',
            'is_redemption' => $item->is_redemption,
            /** What a correct answer is worth (1 for most types; Sudoku 3/5/8, word search 3). */
            'points' => $item->points,
            'payload' => $playable !== null && $type !== null && $registry->has($type) ? $registry->get($type)->forClient($playable) : (object) [],
            'answered' => $item->isAnswered(),
        ];
    }
}
