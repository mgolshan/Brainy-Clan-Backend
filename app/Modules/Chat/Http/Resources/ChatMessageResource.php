<?php

namespace App\Modules\Chat\Http\Resources;

use App\Modules\Chat\Models\ChatMessage;
use App\Modules\Core\Support\Iso;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Identity\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Message (docs/API.md "Family chat"). `body` is the stored plain text, returned verbatim (JSON
 * encoding is the only transformation); null when deleted. An author who is no longer an active
 * member of the family shows as "Former member" (localized for the viewer) with `member_id` and
 * `avatar` null. Needs `author.user` eager-loaded. `reported` is true only when the actions loaded
 * `withExists('reports as reported')` — they do so for family admins only.
 *
 * @property ChatMessage $resource
 */
class ChatMessageResource extends JsonResource
{
    public const array FORMER_MEMBER = ['en' => 'Former member', 'fr' => 'Ancien membre'];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $message = $this->resource;
        $author = $message->author;
        $current = $author instanceof FamilyMember
            && $author->status === FamilyMember::STATUS_ACTIVE
            && $author->family_id === $message->family_id;

        $viewer = $request->user();
        $locale = $viewer instanceof User ? $viewer->locale : 'en';

        return [
            'id' => $message->id,
            'member_id' => $current ? $author->id : null,
            'nickname' => $current ? $author->nickname : (self::FORMER_MEMBER[$locale] ?? self::FORMER_MEMBER['en']),
            'avatar' => $current ? $author->user->avatar : null,
            'body' => $message->isDeleted() ? null : $message->body,
            'deleted' => $message->isDeleted(),
            'client_id' => $message->client_id,
            // Loaded (withExists) only when the viewer is an admin of the family; everyone else
            // always sees false.
            'reported' => array_key_exists('reported', $message->getAttributes()) && (bool) $message->getAttribute('reported'),
            'created_at' => Iso::format($message->created_at),
        ];
    }
}
