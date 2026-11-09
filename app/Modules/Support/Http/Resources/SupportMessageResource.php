<?php

namespace App\Modules\Support\Http\Resources;

use App\Modules\Core\Support\Iso;
use App\Modules\Support\Models\SupportMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * SupportMessage (docs/API.md "Support messages"). `message` is the stored plain text, returned
 * verbatim. `status` is `open` | `answered` | `closed` (a message staff have not seen yet is `open`).
 *
 * @property SupportMessage $resource
 */
class SupportMessageResource extends JsonResource
{
    /**
     * @return array{id: int, topic: string, message: string, status: string, created_at: string|null, answered_at: string|null}
     */
    public function toArray(Request $request): array
    {
        $message = $this->resource;

        return [
            'id' => $message->id,
            'topic' => $message->topic,
            'message' => $message->message,
            'status' => $message->apiStatus(),
            'created_at' => Iso::format($message->created_at),
            'answered_at' => Iso::format($message->answered_at),
        ];
    }
}
