<?php

namespace App\Modules\Billing\Http\Controllers;

use App\Modules\Billing\Actions\RecordAdReward;
use App\Modules\Core\Docs\ApiErrors;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Billing')]
class AdMobSsvController
{
    /**
     * AdMob rewarded-ad server-side verification callback.
     *
     * Public; called by AdMob, authenticated by its ECDSA `signature` over the query string (checked
     * against Google's published keys). `custom_data` is `<reward_ref>:<session_id>` and `user_id`
     * the member id, as set by the app. Idempotent by `transaction_id`: 200 also for duplicates
     * (`outcome: duplicate`) and for callbacks without usable custom data (`ignored`, e.g. AdMob's
     * URL verification). 400 `ssv_signature_invalid` on a missing or bad signature.
     *
     * @unauthenticated
     *
     * @response array{data: array{transaction_id: string|null, outcome: 'stored'|'duplicate'|'ignored'}}
     */
    #[ApiErrors('ssv_signature_invalid')]
    #[QueryParameter('custom_data', description: '`<reward_ref>:<session_id>`', type: 'string', example: '0b6e9c1e-3f1a-4c55-9d0e-6a3c2f9b7e10:12')]
    #[QueryParameter('user_id', description: 'The member id', type: 'string', example: '7')]
    #[QueryParameter('transaction_id', type: 'string', example: '18fa792de1bca816048293fc71035638')]
    #[QueryParameter('signature', description: 'Web-safe base64 DER ECDSA signature (second to last parameter)', type: 'string')]
    #[QueryParameter('key_id', description: 'Google verifier key id (last parameter)', type: 'string', example: '3335741209')]
    public function __invoke(Request $request, RecordAdReward $record): JsonResponse
    {
        $query = $request->server('QUERY_STRING');

        return response()->json(['data' => $record->handle(is_string($query) ? $query : '')]);
    }
}
