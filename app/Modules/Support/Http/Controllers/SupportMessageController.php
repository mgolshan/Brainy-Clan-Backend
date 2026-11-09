<?php

namespace App\Modules\Support\Http\Controllers;

use App\Modules\Core\Docs\ApiErrors;
use App\Modules\Core\Http\CurrentMember;
use App\Modules\Support\Actions\ListSupportMessages;
use App\Modules\Support\Actions\ResolveSupportSender;
use App\Modules\Support\Actions\SubmitSupportMessage;
use App\Modules\Support\Http\Requests\StoreSupportMessageRequest;
use App\Modules\Support\Http\Resources\SupportMessageResource;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Family admins write to the support team; staff answer by email from the admin panel. Everyone
 * else (children, other members, no family) gets 403 `forbidden`.
 */
#[Group('Support', 'Messages from family admins to the Brainy Clan support team. Clients must render messages as plain text.', 10)]
class SupportMessageController
{
    use CurrentMember;

    /**
     * My support messages.
     *
     * The caller's own messages, newest first, at most 50.
     */
    #[ApiErrors('forbidden')]
    public function index(Request $request, ResolveSupportSender $resolve, ListSupportMessages $list): AnonymousResourceCollection
    {
        $user = $this->currentUser($request);
        $resolve->handle($user);

        return SupportMessageResource::collection($list->handle($user));
    }

    /**
     * Write to support.
     *
     * 201 with the new message; re-sending the same `client_id` returns the existing message with
     * 200. The reply goes to the admin's account email. 5 messages per hour and 20 per day per
     * user (429 `rate_limited`).
     */
    #[ApiErrors('forbidden', 'validation_failed', 'rate_limited')]
    public function store(StoreSupportMessageRequest $request, ResolveSupportSender $resolve, SubmitSupportMessage $submit): JsonResponse
    {
        $user = $this->currentUser($request);

        /** @var array{topic: string, message: string, client_id: string, app_version?: string|null, platform?: string|null, device?: string|null} $input */
        $input = $request->validated();
        $result = $submit->handle($user, $resolve->handle($user), $input);

        return (new SupportMessageResource($result['message']))->response()->setStatusCode($result['created'] ? 201 : 200);
    }
}
