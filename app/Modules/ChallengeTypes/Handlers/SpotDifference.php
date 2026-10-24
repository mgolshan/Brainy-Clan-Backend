<?php

namespace App\Modules\ChallengeTypes\Handlers;

use App\Modules\ChallengeTypes\Generators\SpotDifferenceGenerator;
use App\Modules\ChallengeTypes\Support\ClanArt;
use App\Modules\Content\Models\ContentItem;

/**
 * Spot the difference: the app draws `objects` on the left and the same objects with `changes`
 * applied on the right; the member taps the differences.
 *
 * payload:  { "aspect": 1.25, "objects": [ { "id": "o1", "kind": "tree", "x": 0.2, "y": 0.6, "size": 0.18, "color": "green", "flip": false }, … ],
 *             "changes": [ { "id": "d1", "object_id": "o3", "change": "missing" | "color" | "size" | "flip", "value": null | "red" | 0.12 | true } ], "max_misses": 5 }
 * answer:   { }                              (the app needs the changes to draw the right picture)
 * response: { "taps": [[0.71, 0.22], …] }   (≤ 60 taps, normalized 0–1 within the tapped picture: x / width, y / height)
 *
 * `x`, `y` and `size` are fractions of the picture width, so a tap's y is scaled by the height
 * (1 / aspect) before comparing. A tap finds a change when it falls within the changed object's
 * circle (radius = size × 0.75, at least 0.06; for a size change the bigger of both sizes) on
 * either picture; when circles overlap the nearest centre wins; each change counts once (a tap
 * on an already found change is neither a find nor a miss); taps after the last find are ignored. Correct when every change is found with at most `max_misses`
 * taps that hit nothing. 3, 5 or 7 changes by difficulty; worth 2, 3 or 5 points.
 */
class SpotDifference extends BrainGame
{
    public const int MAX_TAPS = 60;

    public const array CHANGE_KINDS = ['missing', 'color', 'size', 'flip'];

    public function __construct(private readonly SpotDifferenceGenerator $generator) {}

    public function key(): string
    {
        return 'spot_difference';
    }

    public function name(): string
    {
        return 'Spot the difference';
    }

    public static function changesFor(int $difficulty): int
    {
        return match (true) {
            $difficulty <= 3 => 3,
            $difficulty <= 7 => 5,
            default => 7,
        };
    }

    /** 8 objects at difficulty 1 up to 14 at difficulty 10. */
    public static function objectsFor(int $difficulty): int
    {
        return 8 + (int) round((self::clampDifficulty($difficulty) - 1) * 6 / 9);
    }

    public static function maxMissesFor(int $difficulty): int
    {
        return $difficulty >= 9 ? 4 : 5;
    }

    public static function pointsForChanges(int $changes): int
    {
        return match (true) {
            $changes <= 3 => 2,
            $changes <= 5 => 3,
            default => 5,
        };
    }

    public function validate(array $payload, array $answer): array
    {
        $aspect = $payload['aspect'] ?? null;

        if ((! is_float($aspect) && ! is_int($aspect)) || $aspect < 0.5 || $aspect > 2) {
            return ['payload.aspect' => ['The aspect (width / height) must be 0.5–2.']];
        }

        $errors = [];
        $objects = $payload['objects'] ?? null;
        $byId = [];

        if (! is_array($objects) || ! array_is_list($objects) || count($objects) < 3 || count($objects) > 30) {
            return ['payload.objects' => ['3–30 objects are required.']];
        }

        $kinds = [...ClanArt::PROPS, ...ClanArt::kidAvatars()];

        foreach ($objects as $index => $object) {
            if (! is_array($object) || ! is_string($object['id'] ?? null) || ! in_array($object['kind'] ?? null, $kinds, true)
                || ! $this->isFraction($object['x'] ?? null) || ! $this->isFraction($object['y'] ?? null)
                || ! $this->isFraction($object['size'] ?? null) || ! in_array($object['color'] ?? null, ClanArt::COLORS, true)
                || ! is_bool($object['flip'] ?? null) || isset($byId[$object['id']])) {
                $errors['payload.objects'][] = "Object {$index} needs a unique id, a known kind, x/y/size from 0 to 1, a palette color and flip.";

                continue;
            }

            $byId[$object['id']] = $object;
        }

        $changes = $payload['changes'] ?? null;

        if (! is_array($changes) || ! array_is_list($changes) || $changes === [] || count($changes) > 10) {
            $errors['payload.changes'][] = '1–10 changes are required.';
        } else {
            $changeIds = [];

            foreach ($changes as $index => $change) {
                if (! is_array($change) || ! is_string($change['id'] ?? null) || isset($changeIds[$change['id']])
                    || ! isset($byId[$change['object_id'] ?? '']) || ! $this->isValidChange($change)) {
                    $errors['payload.changes'][] = "Change {$index} needs a unique id, an object_id and a valid change/value.";

                    continue;
                }

                $changeIds[$change['id']] = true;
            }
        }

        $misses = $payload['max_misses'] ?? null;

        if (! is_int($misses) || $misses < 0 || $misses > 20) {
            $errors['payload.max_misses'][] = 'max_misses must be 0–20.';
        }

        return $errors;
    }

    public function forClient(ContentItem $item): array
    {
        return [
            'aspect' => (float) ($item->payload['aspect'] ?? SpotDifferenceGenerator::ASPECT),
            'objects' => array_values((array) ($item->payload['objects'] ?? [])),
            'changes' => array_values((array) ($item->payload['changes'] ?? [])),
            'max_misses' => (int) ($item->payload['max_misses'] ?? 0),
        ];
    }

    public function validateResponse(ContentItem $item, array $response): array
    {
        $taps = $response['taps'] ?? null;
        $message = 'Send the taps as a list (at most '.self::MAX_TAPS.') of [x, y] pairs from 0 to 1.';

        if (! is_array($taps) || ! array_is_list($taps) || count($taps) > self::MAX_TAPS) {
            return ['response.taps' => [$message]];
        }

        foreach ($taps as $tap) {
            if (! is_array($tap) || ! array_is_list($tap) || count($tap) !== 2 || ! $this->isFraction($tap[0]) || ! $this->isFraction($tap[1])) {
                return ['response.taps' => [$message]];
            }
        }

        return [];
    }

    public function check(ContentItem $item, array $response): bool
    {
        if ($this->validateResponse($item, $response) !== []) {
            return false;
        }

        $aspect = (float) ($item->payload['aspect'] ?? SpotDifferenceGenerator::ASPECT);
        $objects = array_column((array) ($item->payload['objects'] ?? []), null, 'id');
        $targets = [];

        foreach ((array) ($item->payload['changes'] ?? []) as $change) {
            $object = $objects[$change['object_id'] ?? ''] ?? null;

            if (is_array($object)) {
                $targets[] = ['x' => (float) $object['x'], 'y' => (float) $object['y'], 'r' => SpotDifferenceGenerator::hitRadius($object, $change)];
            }
        }

        if ($targets === []) {
            return false;
        }

        $found = [];
        $misses = 0;

        /** @var list<array{0: int|float, 1: int|float}> $taps */
        $taps = $response['taps'];

        foreach ($taps as [$tapX, $tapY]) {
            $x = (float) $tapX;
            $y = (float) $tapY / $aspect;
            $hit = null;
            $nearest = INF;

            foreach ($targets as $index => $target) {
                $distance = hypot($x - $target['x'], $y - $target['y']);

                if ($distance <= $target['r'] && $distance < $nearest) {
                    $hit = $index;
                    $nearest = $distance;
                }
            }

            if ($hit === null) {
                $misses++;
            } elseif (! isset($found[$hit])) {
                $found[$hit] = true;

                if (count($found) === count($targets)) {
                    break;
                }
            }
        }

        return count($found) === count($targets) && $misses <= (int) ($item->payload['max_misses'] ?? 0);
    }

    public function correctResponse(ContentItem $item): array
    {
        return ['changes' => array_values(array_column((array) ($item->payload['changes'] ?? []), 'id'))];
    }

    public function points(ContentItem $item): int
    {
        return self::pointsForChanges(count((array) ($item->payload['changes'] ?? [])));
    }

    public function generate(string $seed, int $difficulty, array $options): array
    {
        $difficulty = self::clampDifficulty($difficulty);
        $scene = $this->generator->generate($seed, $difficulty, self::objectsFor($difficulty), self::changesFor($difficulty));

        return [
            'payload' => [
                'aspect' => SpotDifferenceGenerator::ASPECT,
                'objects' => $scene['objects'],
                'changes' => $scene['changes'],
                'max_misses' => self::maxMissesFor($difficulty),
            ],
            'answer' => [],
        ];
    }

    public function pointsFor(array $options, int $difficulty): int
    {
        return self::pointsForChanges(self::changesFor(self::clampDifficulty($difficulty)));
    }

    private function isFraction(mixed $value): bool
    {
        return (is_float($value) || is_int($value)) && $value >= 0 && $value <= 1;
    }

    /**
     * @param  array<mixed>  $change
     */
    private function isValidChange(array $change): bool
    {
        $value = $change['value'] ?? null;

        return match ($change['change'] ?? null) {
            'missing' => $value === null,
            'flip' => $value === true,
            'color' => in_array($value, ClanArt::COLORS, true),
            'size' => $this->isFraction($value) && $value > 0,
            default => false,
        };
    }
}
