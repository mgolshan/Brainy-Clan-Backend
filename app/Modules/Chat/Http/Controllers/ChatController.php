<?php

namespace App\Modules\Chat\Http\Controllers;

use App\Modules\Chat\Actions\CountUnreadChatMessages;
use App\Modules\Chat\Actions\DeleteChatMessage;
use App\Modules\Chat\Actions\ListChatMessages;
use App\Modules\Chat\Actions\MarkChatRead;
use App\Modules\Chat\Actions\PostChatMessage;
use App\Modules\Chat\Actions\ResolveChatMember;
use App\Modules\Chat\Http\Requests\ListChatMessagesRequest;
use App\Modules\Chat\Http\Requests\MarkChatReadRequest;
use App\Modules\Chat\Http\Requests\PostChatMessageRequest;
use App\Modules\Chat\Http\Resources\ChatMessageResource;
use App\Modules\Core\Docs\ApiErrors;
use App\Modules\Core\Http\CurrentMember;
use App\Modules\Family\Models\FamilyMember;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Only active members of the family can use its chat; everyone else (no family, staff, removed
 * members) gets 404. A member with chat turned off gets 403 `chat_disabled`.
 */
#[Group('Chat', 'Family group chat: plain text with emoji, polling by id cursors. Clients must render messages as plain text.', 8)]
class ChatController
{
    use CurrentMember;

    public function __construct(private readonly ResolveChatMember $resolve) {}

    /**
     * Chat messages.
     *
     * Newest first (no cursor or `before`), oldest first with `after` (polling). `meta` carries
     * `has_more`, `retention_days`, `last_read_id` and `unread`. Messages past the family's
     * retention are not returned.
     */
    #[ApiErrors('chat_disabled')]
    public function index(ListChatMessagesRequest $request, ListChatMessages $list): AnonymousResourceCollection
    {
        $member = $this->chatMember($request);
        $page = $list->handle(
            $member,
            $request->has('before') ? $request->integer('before') : null,
            $request->has('after') ? $request->integer('after') : null,
            $request->integer('limit', ListChatMessages::DEFAULT_LIMIT),
        );

        return ChatMessageResource::collection($page['messages'])->additional(['meta' => [
            'has_more' => $page['has_more'],
            'retention_days' => $page['retention_days'],
            'last_read_id' => $page['last_read_id'],
            'unread' => $page['unread'],
        ]]);
    }

    /**
     * Post a chat message.
     *
     * 201 with the new message; re-sending the same `client_id` returns the existing message with
     * 200 (retries never duplicate). 20 messages per minute per member (429 `rate_limited`).
     */
    #[ApiErrors('chat_disabled')]
    public function store(PostChatMessageRequest $request, PostChatMessage $post): JsonResponse
    {
        $result = $post->handle($this->chatMember($request), $request->string('body')->value(), $request->string('client_id')->value());

        return (new ChatMessageResource($result['message']))->response()->setStatusCode($result['created'] ? 201 : 200);
    }

    /**
     * Delete a chat message.
     *
     * The author or a family admin; the message stays as a tombstone (`deleted: true`, `body: null`).
     */
    #[ApiErrors('chat_disabled', 'forbidden')]
    public function destroy(Request $request, int $message, DeleteChatMessage $delete): Response
    {
        $delete->handle($this->chatMember($request), $message);

        return response()->noContent();
    }

    /**
     * Mark the chat as read.
     *
     * Moves my read marker forward to `last_read_id` (never back).
     */
    #[ApiErrors('chat_disabled')]
    public function read(MarkChatReadRequest $request, MarkChatRead $mark): Response
    {
        $mark->handle($this->chatMember($request), $request->integer('last_read_id'));

        return response()->noContent();
    }

    /**
     * Unread chat messages.
     *
     * Cheap count for the tab badge: `{ "data": { "unread": 3 } }`.
     */
    #[ApiErrors('chat_disabled')]
    public function unread(Request $request, CountUnreadChatMessages $count): JsonResponse
    {
        return response()->json(['data' => ['unread' => $count->handle($this->chatMember($request))]]);
    }

    private function chatMember(Request $request): FamilyMember
    {
        return $this->resolve->handle($this->currentUser($request));
    }
}
