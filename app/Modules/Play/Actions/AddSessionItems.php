<?php

namespace App\Modules\Play\Actions;

use App\Modules\ChallengeTypes\ChallengeTypeRegistry;
use App\Modules\ChallengeTypes\Support\GeneratorSpec;
use App\Modules\Content\Models\ContentItem;
use App\Modules\Play\Models\MemberItemHistory;
use App\Modules\Play\Models\PlaySession;
use App\Modules\Play\Models\SessionItem;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Appends items to a session: content items (counted as "seen" in the member's item history)
 * mixed with generated items (Sudoku, Math Sprint) from the generators the category subtree
 * declares. Each slot picks a source at random: every generator and "content" (when there is
 * any) weigh the same.
 *
 * Items carry `points` (what a correct answer is worth). A batch is cut to the shortest prefix
 * that keeps the target reachable even if every remaining allowed mistake lands on the most
 * valuable unanswered items (see isReachable()). With 1-point items that is exactly the old
 * rule: (target − points) + (cap − mistakes) unanswered items. Redemption rounds always get
 * exactly $count items (they score no points).
 */
class AddSessionItems
{
    private const int UNIQUE_ATTEMPTS = 20;

    public function __construct(
        private readonly PickContentItems $pick,
        private readonly PickGeneratedItems $generated,
        private readonly ChallengeTypeRegistry $registry,
    ) {}

    /**
     * `$isNew`: the session was just created and has no items yet (skips the lookups).
     *
     * @return Collection<int, SessionItem>
     */
    public function handle(PlaySession $session, int $count, bool $redemption = false, bool $isNew = false): Collection
    {
        $member = $session->member;
        $pending = $redemption || $isNew ? [] : $this->pendingPoints($session);

        if ($count <= 0 || (! $redemption && $this->reachable($session, $pending))) {
            return new Collection;
        }

        $existingIds = $isNew ? [] : SessionItem::query()->where('play_session_id', $session->id)->whereNotNull('content_item_id')->pluck('content_item_id')->map(fn ($id) => (int) $id)->all();
        $hasItems = ! $isNew && SessionItem::query()->where('play_session_id', $session->id)->exists();

        $contentItems = $this->pick->handle($member, $session->category_id, $count, $existingIds, easier: $redemption, allowRepeats: $hasItems);

        if ($redemption && $contentItems->count() < $count) {
            // Small categories: allow easy repeats rather than an impossible round.
            $contentItems = $contentItems->merge($this->pick->handle($member, $session->category_id, $count - $contentItems->count(), [], easier: true));
        }

        $specs = $this->generated->specs($member, $session->category_id);

        if ($redemption && $specs !== []) {
            $easiest = min(array_map(fn (GeneratorSpec $spec) => $spec->minDifficulty, $specs));
            $specs = array_values(array_filter($specs, fn (GeneratorSpec $spec) => $spec->minDifficulty === $easiest));
        }

        $slots = $this->plan($session, $count, $redemption, $contentItems->values()->all(), $specs, $pending);

        return $this->store($session, $slots, $redemption, $isNew);
    }

    public function topUp(PlaySession $session): void
    {
        $needed = ($session->target_points - $session->points) + ($session->blunder_cap - $session->mistakes);
        $unanswered = SessionItem::query()
            ->where('play_session_id', $session->id)
            ->where('is_redemption', false)
            ->whereNull('answered_at')
            ->count();

        if ($needed > $unanswered) {
            $this->handle($session, $needed - $unanswered);
        }
    }

    /**
     * Whether unanswered items worth $points can still reach $remainingTarget when the next
     * $spareMistakes wrong answers all land on the most valuable of them.
     *
     * @param  list<int>  $points
     */
    public static function isReachable(array $points, int $remainingTarget, int $spareMistakes): bool
    {
        if ($remainingTarget <= 0) {
            return true;
        }

        rsort($points);

        return array_sum(array_slice($points, max(0, $spareMistakes))) >= $remainingTarget;
    }

    /**
     * A generated item whose question is not already in the session (e.g. "1/3 + 1/3" twice, or
     * the same text as a content item). Small generator pools may run out: after a few tries
     * the last build is kept.
     *
     * @param  int  $level  the difficulty planned for the slot (its points depend on it)
     * @param  array<string, true>  $seen  question keys in the session; the new key is added
     * @return array{type_key: string, category_id: int, seed: string, difficulty: int, points: int, payload: array<string, mixed>, answer: array<string, mixed>}
     */
    private function buildUnique(GeneratorSpec $spec, int $level, PlaySession $session, array &$seen): array
    {
        for ($attempt = 0; $attempt < self::UNIQUE_ATTEMPTS; $attempt++) {
            $built = $this->generated->build($spec, $session->member, $level);
            $key = self::questionKey($built['payload']);

            if ($key === null || ! isset($seen[$key])) {
                break;
            }
        }

        if ($key !== null) {
            $seen[$key] = true;
        }

        return $built;
    }

    /**
     * Question keys of the items already in the session plus the content items about to be added.
     *
     * @param  list<array{content: ContentItem}|array{spec: GeneratorSpec, level: int}>  $slots
     * @return array<string, true>
     */
    private function questionKeys(PlaySession $session, array $slots, bool $isNew): array
    {
        $payloads = $isNew ? [] : SessionItem::query()
            ->where('play_session_id', $session->id)
            ->with('contentItem:id,payload')
            ->get(['id', 'content_item_id', 'payload'])
            ->map(fn (SessionItem $item) => $item->payload ?? $item->contentItem?->payload)
            ->all();

        foreach ($slots as $slot) {
            if (isset($slot['content'])) {
                $payloads[] = $slot['content']->payload;
            }
        }

        $keys = [];

        foreach ($payloads as $payload) {
            $key = is_array($payload) ? self::questionKey($payload) : null;

            if ($key !== null) {
                $keys[$key] = true;
            }
        }

        return $keys;
    }

    /**
     * Normalized question text ("1/3 + 1/3" = "1/3+1/3"), or the whole payload for types without
     * a question (Sudoku grids).
     *
     * @param  array<string, mixed>  $payload
     */
    public static function questionKey(array $payload): ?string
    {
        if (is_string($payload['question'] ?? null)) {
            $text = (string) preg_replace('/\s+/u', '', mb_strtolower($payload['question']));

            return $text !== '' ? 'q:'.$text : null;
        }

        return $payload !== [] ? 'p:'.md5((string) json_encode($payload)) : null;
    }

    /**
     * @param  list<ContentItem>  $content
     * @param  list<GeneratorSpec>  $specs
     * @param  list<int>  $pending
     * @return list<array{content: ContentItem}|array{spec: GeneratorSpec, level: int}>
     */
    private function plan(PlaySession $session, int $count, bool $redemption, array $content, array $specs, array $pending): array
    {
        $slots = [];
        $points = $pending;

        for ($i = 0; $i < $count; $i++) {
            $sources = count($specs) + ($content !== [] ? 1 : 0);

            if ($sources === 0) {
                break;
            }

            $choice = random_int(0, $sources - 1);

            if ($choice < count($specs)) {
                $level = $this->generated->level($specs[$choice], $session->member, easier: $redemption);
                $slots[] = ['spec' => $specs[$choice], 'level' => $level];
                $points[] = $this->generated->points($specs[$choice], $level);
            } else {
                /** @var ContentItem $item */
                $item = array_shift($content);
                $slots[] = ['content' => $item];
                $points[] = $this->contentPoints($item);
            }

            if (! $redemption && $this->reachable($session, $points)) {
                break;
            }
        }

        return $slots;
    }

    /**
     * Inserts the batch with one query, bumps the member's history of the content items with one
     * upsert (`times_seen` + 1, `last_seen_at` now; a content item twice in a batch counts twice)
     * and returns the stored rows.
     *
     * @param  list<array{content: ContentItem}|array{spec: GeneratorSpec, level: int}>  $slots
     * @return Collection<int, SessionItem>
     */
    private function store(PlaySession $session, array $slots, bool $redemption, bool $isNew): Collection
    {
        if ($slots === []) {
            return new Collection;
        }

        $firstPosition = $isNew ? 0 : (int) SessionItem::query()->where('play_session_id', $session->id)->max('position');
        $position = $firstPosition;
        $seen = $this->questionKeys($session, $slots, $isNew);
        $now = now();
        $rows = [];
        $history = [];

        foreach ($slots as $slot) {
            if (isset($slot['spec'])) {
                $built = $this->buildUnique($slot['spec'], $slot['level'], $session, $seen);

                $rows[] = $this->row([
                    'play_session_id' => $session->id,
                    'content_item_id' => null,
                    'type_key' => $built['type_key'],
                    'category_id' => $built['category_id'],
                    'seed' => $built['seed'],
                    'position' => ++$position,
                    'is_redemption' => $redemption,
                    'points' => $built['points'],
                    'payload' => $built['payload'],
                    'answer' => $built['answer'],
                ], $now);

                continue;
            }

            $contentItem = $slot['content'];

            $rows[] = $this->row([
                'play_session_id' => $session->id,
                'content_item_id' => $contentItem->id,
                'type_key' => $contentItem->type_key,
                'category_id' => null,
                'seed' => null,
                'position' => ++$position,
                'is_redemption' => $redemption,
                'points' => $this->contentPoints($contentItem),
                'payload' => null,
                'answer' => null,
            ], $now);

            $history[] = [
                'member_id' => $session->member_id,
                'content_item_id' => $contentItem->id,
                'times_seen' => 1,
                'last_seen_at' => (new MemberItemHistory)->fromDateTime($now),
            ];
        }

        SessionItem::query()->insert($rows);

        if ($history !== []) {
            MemberItemHistory::query()->upsert(
                $history,
                ['member_id', 'content_item_id'],
                ['times_seen' => DB::raw('times_seen + 1'), 'last_seen_at'],
            );
        }

        return SessionItem::query()
            ->where('play_session_id', $session->id)
            ->where('position', '>', $firstPosition)
            ->orderBy('position')
            ->get();
    }

    /**
     * A session_items row with the model's casts applied (JSON columns encoded) and timestamps.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function row(array $attributes, CarbonInterface $now): array
    {
        $item = (new SessionItem)->forceFill($attributes);
        $item->setCreatedAt($now);
        $item->setUpdatedAt($now);

        return $item->getAttributes();
    }

    /**
     * @param  list<int>  $points
     */
    private function reachable(PlaySession $session, array $points): bool
    {
        return self::isReachable($points, $session->target_points - $session->points, $session->blunder_cap - $session->mistakes);
    }

    /**
     * @return list<int>
     */
    private function pendingPoints(PlaySession $session): array
    {
        return SessionItem::query()
            ->where('play_session_id', $session->id)
            ->where('is_redemption', false)
            ->whereNull('answered_at')
            ->pluck('points')
            ->map(fn ($points) => (int) $points)
            ->values()
            ->all();
    }

    private function contentPoints(ContentItem $item): int
    {
        return $this->registry->has($item->type_key) ? max(1, $this->registry->get($item->type_key)->points($item)) : 1;
    }
}
