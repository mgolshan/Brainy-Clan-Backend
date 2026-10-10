<?php

namespace App\Modules\Play\Http\Resources;

use App\Modules\Core\Support\Iso;
use App\Modules\Play\Models\PlaySession;
use App\Modules\Play\Models\SessionItem;
use App\Modules\Policy\PolicyResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property PlaySession $resource
 */
class SessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $session = $this->resource;
        $session->loadMissing('member.family', 'member.user');
        $rules = app(PolicyResolver::class)->secondChanceForMember($session->member);

        return [
            'id' => $session->id,
            'category_id' => $session->category_id,
            'status' => $session->status,
            'target_points' => $session->target_points,
            'blunder_cap' => $session->blunder_cap,
            'mistakes' => $session->mistakes,
            'points' => $session->points,
            'answered' => SessionItem::query()->where('play_session_id', $session->id)->where('is_redemption', false)->whereNotNull('answered_at')->count(),
            'second_chances_used' => $session->second_chances_used,
            'second_chance' => [
                'free_remaining_today' => $rules['free_remaining_today'],
                'ad_available' => $rules['ad_allowed'],
                'redemption_available' => $rules['redemption_allowed'] && ! $session->redemption_failed,
            ],
            'started_at' => Iso::format($session->started_at),
            'completed_at' => Iso::format($session->completed_at),
        ];
    }
}
