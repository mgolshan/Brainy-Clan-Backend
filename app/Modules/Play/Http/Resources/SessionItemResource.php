<?php

namespace App\Modules\Play\Http\Resources;

use App\Modules\ChallengeTypes\ChallengeTypeRegistry;
use App\Modules\Play\Models\SessionItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A served item with its answer stripped (the plugin's forClient()).
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
        $content = $item->contentItem;
        $registry = app(ChallengeTypeRegistry::class);

        return [
            'id' => $item->id,
            'position' => $item->position,
            'type' => $content?->type_key ?? 'unknown',
            'is_redemption' => $item->is_redemption,
            'payload' => $content !== null && $registry->has($content->type_key) ? $registry->get($content->type_key)->forClient($content) : (object) [],
            'answered' => $item->isAnswered(),
        ];
    }
}
