<?php

namespace App\Modules\ChallengeTypes\Generators;

use App\Modules\ChallengeTypes\Support\ClanArt;
use Random\Randomizer;
use RuntimeException;

/**
 * Seeded spot-the-difference scenes.
 *
 * Coordinates are fractions of the picture width (the height is 1 / ASPECT = 0.8 of it). A scene
 * places 8–14 non-overlapping objects (Clan kid avatars and props, distinct kinds, palette
 * colors), then picks the changes among objects big enough to tap (size ≥ 0.1) whose hit circles
 * (see hitRadius()) do not overlap each other: `missing`, `size` (×1.5 or ×0.6; growing only
 * when the bigger object still clears its neighbours), `color` (props only, to another palette
 * color) and `flip` (asymmetric props only). Avatars only go missing or change size, so every
 * change is visible whatever the app's avatar art looks like. Same seed and difficulty → same
 * scene.
 */
class SpotDifferenceGenerator
{
    /** Picture width / height. */
    public const float ASPECT = 1.25;

    /** Hit circle radius = size × HIT_SCALE, at least MIN_HIT_RADIUS. */
    public const float HIT_SCALE = 0.75;

    public const float MIN_HIT_RADIUS = 0.06;

    /** Smallest object a change may be put on. */
    public const float MIN_CHANGE_SIZE = 0.1;

    private const float MIN_SIZE = 0.08;

    private const float MAX_SIZE = 0.16;

    /** Gap kept between objects and to the picture edge. */
    private const float GAP = 0.015;

    private const int LAYOUT_ATTEMPTS = 200;

    /**
     * @return array{objects: list<array{id: string, kind: string, x: float, y: float, size: float, color: string, flip: bool}>, changes: list<array{id: string, object_id: string, change: string, value: string|float|bool|null}>}
     */
    public function generate(string $seed, int $difficulty, int $objectCount, int $changeCount): array
    {
        $rng = ClanArt::random('spot_difference', $seed, $difficulty);

        for ($attempt = 0; $attempt < self::LAYOUT_ATTEMPTS; $attempt++) {
            $objects = $this->layout($rng, $objectCount);

            if (count($objects) < $objectCount) {
                continue;
            }

            $changes = $this->pickChanges($rng, $objects, $changeCount);

            if ($changes !== null) {
                return ['objects' => $objects, 'changes' => $changes];
            }
        }

        throw new RuntimeException("No spot-the-difference scene with {$changeCount} changes for seed [{$seed}].");
    }

    /**
     * Radius of a change's hit circle: the bigger of the object's sizes in both pictures.
     *
     * @param  array{size: float|int}  $object
     * @param  array{change: string, value: mixed}  $change
     */
    public static function hitRadius(array $object, array $change): float
    {
        $size = (float) $object['size'];

        if ($change['change'] === 'size' && (is_float($change['value']) || is_int($change['value']))) {
            $size = max($size, (float) $change['value']);
        }

        return max(self::MIN_HIT_RADIUS, $size * self::HIT_SCALE);
    }

    /**
     * @return list<array{id: string, kind: string, x: float, y: float, size: float, color: string, flip: bool}>
     */
    private function layout(Randomizer $rng, int $count): array
    {
        $propCount = min(count(ClanArt::PROPS), (int) ceil($count * 0.6));
        $kinds = $rng->shuffleArray([
            ...array_slice($rng->shuffleArray(ClanArt::PROPS), 0, $propCount),
            ...array_slice($rng->shuffleArray(ClanArt::kidAvatars()), 0, $count - $propCount),
        ]);
        $height = 1 / self::ASPECT;
        $objects = [];

        foreach ($kinds as $kind) {
            $size = round($rng->getFloat(self::MIN_SIZE, self::MAX_SIZE), 3);
            $margin = $size / 2 + self::GAP;

            for ($try = 0; $try < 60; $try++) {
                $x = round($rng->getFloat($margin, 1 - $margin), 3);
                $y = round($rng->getFloat($margin, $height - $margin), 3);

                if ($this->clears($objects, $x, $y, $size)) {
                    $objects[] = [
                        'id' => 'o'.(count($objects) + 1),
                        'kind' => $kind,
                        'x' => $x,
                        'y' => $y,
                        'size' => $size,
                        'color' => ClanArt::COLORS[$rng->getInt(0, count(ClanArt::COLORS) - 1)],
                        'flip' => in_array($kind, ClanArt::ASYMMETRIC_PROPS, true) && $rng->getInt(0, 1) === 1,
                    ];
                    break;
                }
            }
        }

        return $objects;
    }

    /**
     * @param  list<array{id: string, kind: string, x: float, y: float, size: float, color: string, flip: bool}>  $objects
     * @return list<array{id: string, object_id: string, change: string, value: string|float|bool|null}>|null
     */
    private function pickChanges(Randomizer $rng, array $objects, int $count): ?array
    {
        $changes = [];
        $circles = [];

        foreach ($rng->shuffleArray($objects) as $object) {
            if (count($changes) === $count) {
                break;
            }

            if ($object['size'] < self::MIN_CHANGE_SIZE) {
                continue;
            }

            $change = $this->changeFor($rng, $object, $objects);
            $radius = self::hitRadius($object, $change);

            foreach ($circles as [$x, $y, $r]) {
                if (hypot($object['x'] - $x, $object['y'] - $y) < $radius + $r) {
                    continue 2;
                }
            }

            $circles[] = [$object['x'], $object['y'], $radius];
            $changes[] = ['id' => 'd'.(count($changes) + 1), 'object_id' => $object['id'], ...$change];
        }

        return count($changes) === $count ? $changes : null;
    }

    /**
     * @param  array{id: string, kind: string, x: float, y: float, size: float, color: string, flip: bool}  $object
     * @param  list<array{id: string, kind: string, x: float, y: float, size: float, color: string, flip: bool}>  $objects
     * @return array{change: string, value: string|float|bool|null}
     */
    private function changeFor(Randomizer $rng, array $object, array $objects): array
    {
        $isProp = in_array($object['kind'], ClanArt::PROPS, true);
        $kinds = ['missing', 'size'];

        if ($isProp) {
            $kinds[] = 'color';
            $kinds[] = 'color';
        }

        if (in_array($object['kind'], ClanArt::ASYMMETRIC_PROPS, true)) {
            $kinds[] = 'flip';
        }

        $kind = $kinds[$rng->getInt(0, count($kinds) - 1)];

        return match ($kind) {
            'missing' => ['change' => 'missing', 'value' => null],
            'flip' => ['change' => 'flip', 'value' => true],
            'color' => ['change' => 'color', 'value' => $this->otherColor($rng, $object['color'])],
            default => ['change' => 'size', 'value' => $this->newSize($rng, $object, $objects)],
        };
    }

    private function otherColor(Randomizer $rng, string $color): string
    {
        $others = array_values(array_diff(ClanArt::COLORS, [$color]));

        return $others[$rng->getInt(0, count($others) - 1)];
    }

    /**
     * ×1.5 when the grown object still clears its neighbours and the edges (and a coin says
     * grow), else ×0.6.
     *
     * @param  array{id: string, kind: string, x: float, y: float, size: float, color: string, flip: bool}  $object
     * @param  list<array{id: string, kind: string, x: float, y: float, size: float, color: string, flip: bool}>  $objects
     */
    private function newSize(Randomizer $rng, array $object, array $objects): float
    {
        $grown = round($object['size'] * 1.5, 3);
        $others = array_values(array_filter($objects, fn (array $other) => $other['id'] !== $object['id']));
        $half = $grown / 2;
        $fits = $object['x'] - $half >= 0 && $object['x'] + $half <= 1
            && $object['y'] - $half >= 0 && 1 / self::ASPECT >= $object['y'] + $half
            && $this->clears($others, $object['x'], $object['y'], $grown);

        return $fits && $rng->getInt(0, 1) === 1 ? $grown : round($object['size'] * 0.6, 3);
    }

    /**
     * @param  list<array{id: string, kind: string, x: float, y: float, size: float, color: string, flip: bool}>  $objects
     */
    private function clears(array $objects, float $x, float $y, float $size): bool
    {
        foreach ($objects as $other) {
            if (hypot($x - $other['x'], $y - $other['y']) < ($size + $other['size']) / 2 + self::GAP) {
                return false;
            }
        }

        return true;
    }
}
