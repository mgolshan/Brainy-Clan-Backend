<?php

namespace App\Modules\Chat\Support;

use App\Modules\Chat\Models\ChatMessage;
use App\Modules\Chat\Models\ChatMessageReaction;
use App\Modules\Family\Models\FamilyMember;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Throwable;

/**
 * Family chat reactions (docs/API.md "Family chat" → "Reactions", 2026-11-11).
 *
 * - {@see self::KEYS}: the fixed, kid-safe set, in API order (the app maps keys to emoji).
 * - {@see self::summary()}: a message's `reactions` field — only keys with a count, in KEYS order,
 *   `member_ids` in reaction order (row id), `mine` for the viewer; `[]` for a deleted message.
 *   Former members' reactions are filtered out by the eager-load constraint
 *   ({@see self::eagerLoad()}: only reactions of active members).
 * - Change marker: `chat_messages.reactions_changed_at` (microseconds), bumped by
 *   {@see self::markChanged()}; the polling cursor is an opaque base64url of "changed_at|id".
 */
final class ChatReactions
{
    /** @var list<string> */
    public const array KEYS = ['heart', 'thumbs_up', 'laugh', 'wow', 'party', 'star'];

    /** Rows per `GET /family/chat/reactions` page. */
    public const int PAGE_SIZE = 200;

    /**
     * A poll's cursor never moves past "now − this many seconds" (unless the page is full), so a
     * change whose transaction commits a little late — with an earlier timestamp than one already
     * seen — is still listed on the next poll. Re-listing a message is harmless: rows are full
     * snapshots.
     */
    public const int SAFETY_SECONDS = 2;

    private const string FORMAT = 'Y-m-d H:i:s.u';

    /**
     * Eager-load constraint for `ChatMessage::reactions`: active members only, in reaction order.
     * One query for any number of messages (the member check is an EXISTS subquery).
     *
     * @return array{reactions: \Closure(HasMany<ChatMessageReaction, ChatMessage>): void}
     */
    public static function eagerLoad(): array
    {
        return ['reactions' => function (HasMany $query): void {
            $query->select(['id', 'chat_message_id', 'member_id', 'key'])
                ->whereHas('member', fn (Builder $member) => $member->where('status', FamilyMember::STATUS_ACTIVE))
                ->orderBy('id');
        }];
    }

    /**
     * @return list<array{key: string, count: int, mine: bool, member_ids: list<int>}>
     */
    public static function summary(ChatMessage $message, ?int $viewerMemberId): array
    {
        if ($message->isDeleted()) {
            return [];
        }

        if (! $message->relationLoaded('reactions')) {
            $message->load(self::eagerLoad());
        }

        $byKey = [];

        foreach ($message->reactions as $reaction) {
            $byKey[$reaction->key][] = $reaction->member_id;
        }

        $summary = [];

        foreach (self::KEYS as $key) {
            $memberIds = $byKey[$key] ?? [];

            if ($memberIds !== []) {
                $summary[] = [
                    'key' => $key,
                    'count' => count($memberIds),
                    'mine' => $viewerMemberId !== null && in_array($viewerMemberId, $memberIds, true),
                    'member_ids' => $memberIds,
                ];
            }
        }

        return $summary;
    }

    /**
     * Bumps the change marker of these messages (one UPDATE).
     *
     * @param  list<int>|Builder<ChatMessageReaction>|\Illuminate\Database\Query\Builder  $messageIds  ids, or a subquery selecting them
     */
    public static function markChanged(array|Builder|\Illuminate\Database\Query\Builder $messageIds): void
    {
        if ($messageIds === []) {
            return;
        }

        ChatMessage::query()
            ->whereIn('id', $messageIds)
            ->toBase()
            ->update(['reactions_changed_at' => self::stamp(CarbonImmutable::now())]);
    }

    public static function stamp(CarbonImmutable $at): string
    {
        return $at->utc()->format(self::FORMAT);
    }

    public static function encodeCursor(string $changedAt, int $id): string
    {
        return rtrim(strtr(base64_encode($changedAt.'|'.$id), '+/', '-_'), '=');
    }

    /**
     * The cursor of a message row's change marker.
     */
    public static function cursorOf(ChatMessage $message): string
    {
        $changedAt = CarbonImmutable::parse((string) $message->getRawOriginal('reactions_changed_at'), 'UTC');

        return self::encodeCursor(self::stamp($changedAt), $message->id);
    }

    /**
     * "Now minus the safety window", before every id at that instant.
     */
    public static function safeCursor(): string
    {
        return self::encodeCursor(self::stamp(CarbonImmutable::now()->subSeconds(self::SAFETY_SECONDS)), 0);
    }

    /**
     * @return array{0: string, 1: int}|null [changed_at, id], or null when malformed
     */
    public static function decodeCursor(string $cursor): ?array
    {
        if ($cursor === '' || strlen($cursor) > 100 || preg_match('/^[A-Za-z0-9_-]+$/', $cursor) !== 1) {
            return null;
        }

        $decoded = base64_decode(strtr($cursor, '-_', '+/'), true);

        if ($decoded === false || preg_match('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{6})\|(\d{1,19})$/', $decoded, $matches) !== 1) {
            return null;
        }

        try {
            $at = CarbonImmutable::createFromFormat(self::FORMAT, $matches[1], 'UTC');
        } catch (Throwable) {
            return null;
        }

        if (! $at instanceof CarbonImmutable || $at->format(self::FORMAT) !== $matches[1]) {
            return null;
        }

        return [$matches[1], (int) $matches[2]];
    }

    /**
     * The later of two cursors (by changed_at, then id).
     */
    public static function maxCursor(string $a, string $b): string
    {
        $left = self::decodeCursor($a);
        $right = self::decodeCursor($b);

        if ($left === null || $right === null) {
            return $left === null ? $b : $a;
        }

        $order = strcmp($left[0], $right[0]) ?: $left[1] <=> $right[1];

        return $order >= 0 ? $a : $b;
    }
}
