<?php

use App\Modules\ChallengeTypes\Generators\NonogramGenerator;
use App\Modules\ChallengeTypes\Generators\SpotDifferenceGenerator;
use App\Modules\ChallengeTypes\Handlers\MemoryMatch;
use App\Modules\ChallengeTypes\Handlers\Nonogram;
use App\Modules\ChallengeTypes\Handlers\PatternSequence;
use App\Modules\ChallengeTypes\Handlers\SpotDifference;
use App\Modules\ChallengeTypes\Support\ClanArt;
use App\Modules\Content\Models\ContentItem;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;

/**
 * Brain games (docs/API.md "Brain games"): generators are deterministic and valid at every
 * difficulty, nonogram pictures are uniquely solvable, verifiers replay the moves.
 */
function bgItem(string $type, array $generated): ContentItem
{
    return (new ContentItem)->forceFill(['type_key' => $type, 'payload' => $generated['payload'], 'answer' => $generated['answer']]);
}

/**
 * A perfect-memory player: takes a known pair when there is one, else turns an unknown card and
 * its known mate (or a second unknown card). Turns cards left to right.
 *
 * @param  list<string>  $cards
 * @return list<array{0: int, 1: int}>
 */
function bgPerfectMemory(array $cards): array
{
    $seen = [];
    $matched = [];
    $attempts = [];
    $next = 0;
    $count = count($cards);

    $nextUnknown = function () use (&$next, &$matched, $count): ?int {
        while ($next < $count && isset($matched[$next])) {
            $next++;
        }

        return $next < $count ? $next++ : null;
    };

    while (count($matched) < $count) {
        $pair = null;

        foreach ($seen as $face => $positions) {
            $open = array_values(array_filter($positions, fn (int $p) => ! isset($matched[$p])));

            if (count($open) === 2) {
                $pair = $open;
                break;
            }
        }

        if ($pair === null) {
            $first = $nextUnknown();
            $mate = collect($seen[$cards[$first]] ?? [])->first(fn (int $p) => $p !== $first && ! isset($matched[$p]));
            $second = $mate ?? $nextUnknown();
            $pair = [$first, $second];
        }

        $attempts[] = $pair;

        foreach ($pair as $position) {
            $seen[$cards[$position]][] = $position;
            $seen[$cards[$position]] = array_values(array_unique($seen[$cards[$position]]));
        }

        if ($cards[$pair[0]] === $cards[$pair[1]]) {
            $matched[$pair[0]] = $matched[$pair[1]] = true;
        }
    }

    return $attempts;
}

/**
 * Taps on the centre of every changed object (y normalized by the picture height).
 *
 * @return list<array{0: float, 1: float}>
 */
function bgCentreTaps(array $payload): array
{
    $objects = array_column($payload['objects'], null, 'id');

    return array_map(fn (array $change) => [
        $objects[$change['object_id']]['x'],
        $objects[$change['object_id']]['y'] * $payload['aspect'],
    ], $payload['changes']);
}

dataset('brain games', ['memory_match', 'pattern_sequence', 'nonogram', 'spot_difference']);

it('generates the same item for the same seed and a valid item at every difficulty', function (string $type) {
    $handler = app('App\\Modules\\ChallengeTypes\\Handlers\\'.str_replace('_', '', ucwords($type, '_')));
    $payloads = [];

    foreach (range(1, 10) as $difficulty) {
        foreach (range(1, 25) as $n) {
            $item = $handler->generate("seed-{$n}", $difficulty, []);

            expect($handler->generate("seed-{$n}", $difficulty, []))->toBe($item)
                ->and($handler->validate($item['payload'], $item['answer']))->toBe([])
                ->and($handler->points(bgItem($type, $item)))->toBe($handler->pointsFor([], $difficulty))
                ->and($handler->forClient(bgItem($type, $item)))->toEqual($item['payload']);

            $payloads[json_encode($item['payload'])] = true;
        }
    }

    expect(count($payloads))->toBeGreaterThan(30);
})->with('brain games');

it('sizes memory match by difficulty with fair attempt limits', function () {
    $handler = app(MemoryMatch::class);
    $kids = ClanArt::kidAvatars();

    expect($kids)->toContain('fox', 'owl', 'monkey')->not->toContain('fox-teen');

    foreach (range(1, 10) as $difficulty) {
        foreach (range(1, 30) as $n) {
            $payload = $handler->generate("m{$n}", $difficulty, [])['payload'];
            $pairs = MemoryMatch::PAIRS[$difficulty];

            expect($payload['cards'])->toHaveCount($pairs * 2)
                ->and($payload['cols'])->toBe(MemoryMatch::COLS[$pairs])
                ->and(array_diff($payload['cards'], $kids))->toBe([])
                ->and(array_unique(array_count_values($payload['cards'])))->toBe([$payload['cards'][0] => 2])
                ->and($payload['max_attempts'])->toBe($difficulty <= 4 ? 2 * $pairs + 2 : 2 * $pairs + 1);

            // A perfect memory never needs more than 2 × pairs − 1 attempts, so it always wins.
            $perfect = bgPerfectMemory($payload['cards']);
            expect(count($perfect))->toBeLessThanOrEqual(2 * $pairs - 1)
                ->and($handler->check(bgItem('memory_match', ['payload' => $payload, 'answer' => []]), ['attempts' => $perfect]))->toBeTrue();
        }
    }

    expect(MemoryMatch::pairsFor(1))->toBe(3)->and(MemoryMatch::pairsFor(4))->toBe(4)->and(MemoryMatch::pairsFor(6))->toBe(6)
        ->and(MemoryMatch::pairsFor(8))->toBe(8)->and(MemoryMatch::pairsFor(10))->toBe(10);
});

it('replays memory match attempts', function () {
    $handler = app(MemoryMatch::class);
    $item = bgItem('memory_match', ['payload' => ['cols' => 3, 'cards' => ['fox', 'owl', 'fox', 'frog', 'owl', 'frog'], 'max_attempts' => 6], 'answer' => []]);

    $perfect = [[0, 2], [1, 4], [3, 5]];
    $slow = [[0, 1], [0, 3], [1, 3], [2, 4], [0, 2], [1, 4], [3, 5]];

    expect($handler->validateResponse($item, ['attempts' => $perfect]))->toBe([])
        ->and($handler->check($item, ['attempts' => $perfect]))->toBeTrue()
        ->and($handler->check($item, ['attempts' => array_slice($slow, 0, 6)]))->toBeFalse()   // not all matched
        ->and($handler->check($item, ['attempts' => $slow]))->toBeFalse()                       // 7 > max_attempts 6
        ->and($handler->check($item, ['attempts' => []]))->toBeFalse()
        ->and($handler->correctResponse($item))->toBe(['attempts' => null, 'cards' => ['fox', 'owl', 'fox', 'frog', 'owl', 'frog']])
        ->and($handler->points($item))->toBe(2);

    foreach ([
        ['attempts' => [[0, 6]]],           // out of range
        ['attempts' => [[1, 1]]],           // same position twice
        ['attempts' => [[0, 2], [2, 3]]],   // already matched
        ['attempts' => [[0]]],
        ['attempts' => [['0', '2']]],
        ['attempts' => 'oops'],
        [],
        ['attempts' => array_fill(0, 201, [0, 1])],
    ] as $response) {
        expect($handler->validateResponse($item, $response))->toHaveKey('response.attempts');
    }
});

it('builds pattern sequences by difficulty and checks them exactly', function () {
    $handler = app(PatternSequence::class);

    foreach (range(1, 10) as $difficulty) {
        foreach (range(1, 40) as $n) {
            $payload = $handler->generate("p{$n}", $difficulty, [])['payload'];

            expect($payload['pads'])->toHaveCount(PatternSequence::PADS[$difficulty])
                ->and(array_unique($payload['pads']))->toHaveCount(count($payload['pads']))
                ->and(array_diff($payload['pads'], ClanArt::kidAvatars()))->toBe([])
                ->and($payload['sequence'])->toHaveCount(PatternSequence::LENGTHS[$difficulty])
                ->and(PatternSequence::hasTripleRepeat($payload['sequence']))->toBeFalse()
                ->and(max($payload['sequence']))->toBeLessThan(count($payload['pads']));
        }
    }

    expect(PatternSequence::PADS[2])->toBe(3)->and(PatternSequence::PADS[3])->toBe(4)->and(PatternSequence::PADS[6])->toBe(4)
        ->and(PatternSequence::PADS[7])->toBe(5)->and(PatternSequence::PADS[10])->toBe(6)
        ->and(PatternSequence::LENGTHS[1])->toBe(3)->and(PatternSequence::LENGTHS[10])->toBe(9)
        ->and(PatternSequence::showMsFor(1))->toBe(900)->and(PatternSequence::showMsFor(10))->toBe(500)
        ->and(PatternSequence::showMsFor(6))->toBeLessThan(PatternSequence::showMsFor(5))
        ->and(PatternSequence::pointsForLength(4))->toBe(1)->and(PatternSequence::pointsForLength(5))->toBe(2)
        ->and(PatternSequence::pointsForLength(6))->toBe(2)->and(PatternSequence::pointsForLength(7))->toBe(3);

    $item = bgItem('pattern_sequence', ['payload' => ['pads' => ['fox', 'owl', 'frog'], 'sequence' => [2, 0, 1, 1], 'show_ms' => 800], 'answer' => []]);

    expect($handler->check($item, ['input' => [2, 0, 1, 1]]))->toBeTrue()
        ->and($handler->check($item, ['input' => [2, 0, 1]]))->toBeFalse()
        ->and($handler->check($item, ['input' => [2, 0, 1, 1, 0]]))->toBeFalse()
        ->and($handler->check($item, ['input' => [2, 0, 1, 0]]))->toBeFalse()
        ->and($handler->validateResponse($item, ['input' => []]))->toBe([])
        ->and($handler->validateResponse($item, ['input' => [3]]))->toHaveKey('response.input')
        ->and($handler->validateResponse($item, ['input' => ['2']]))->toHaveKey('response.input')
        ->and($handler->validateResponse($item, ['input' => array_fill(0, 21, 0)]))->toHaveKey('response.input')
        ->and($handler->validateResponse($item, []))->toHaveKey('response.input')
        ->and($handler->correctResponse($item))->toBe(['input' => [2, 0, 1, 1]])
        ->and($handler->points($item))->toBe(1);
});

it('has at least 12 uniquely solvable nonogram pictures per size with en and fr titles', function (int $size) {
    $generator = new NonogramGenerator;
    $library = NonogramGenerator::library()[$size];

    expect(count($library))->toBeGreaterThanOrEqual(12);

    foreach ($library as $key => $picture) {
        $grid = NonogramGenerator::toGrid($picture['rows']);

        expect($picture['en'])->not->toBe('')->and($picture['fr'])->not->toBe('')
            ->and($generator->isUsable($grid, $size))->toBeTrue("Nonogram picture {$size}/{$key} is not uniquely solvable line by line (or has an empty row/column).");
    }

    expect($generator->usablePictures($size))->toHaveCount(count($library));
})->with([5, 8, 10]);

it('solves nonograms line by line and rejects ambiguous ones', function () {
    $generator = new NonogramGenerator;

    // A 2×2 diagonal has two solutions: line logic cannot settle it.
    expect($generator->solve([[1], [1]], [[1], [1]]))->toBeNull()
        ->and($generator->solve([[2], [2]], [[2], [2]]))->toBe([[1, 1], [1, 1]])
        ->and($generator->solve([[3], [2]], [[2], [2]]))->toBeNull()   // contradiction
        ->and($generator->solveLine([3], [-1, -1, -1, -1]))->toBe([-1, 1, 1, -1])
        ->and(NonogramGenerator::lineClues([1, 1, 0, 1, 0, 1, 1, 1]))->toBe([2, 1, 3]);

    $rng = new Randomizer(new Xoshiro256StarStar(hash('sha256', 'fallback', true)));

    foreach ([5, 8] as $size) {
        $grid = $generator->randomPicture($rng, $size);

        expect($generator->isUsable($grid, $size))->toBeTrue();
    }
});

it('generates nonograms by difficulty, verifies clues and reveals a localized title', function () {
    $handler = app(Nonogram::class);

    foreach ([1 => 5, 3 => 5, 5 => 5, 6 => 8, 8 => 8, 9 => 10, 10 => 10] as $difficulty => $size) {
        $item = $handler->generate("n{$difficulty}", $difficulty, ['locale' => 'en']);

        expect($item['payload']['rows'])->toBe($size)->and($item['payload']['cols'])->toBe($size)
            ->and($handler->pointsFor([], $difficulty))->toBe(Nonogram::POINTS[$size])
            ->and(json_encode($handler->forClient(bgItem('nonogram', $item))))->not->toContain('solution');
    }

    $en = $handler->generate('title', 4, ['locale' => 'en']);
    $fr = $handler->generate('title', 4, ['locale' => 'fr']);
    $picture = NonogramGenerator::library()[5][$en['answer']['picture']];

    expect($fr['payload'])->toBe($en['payload'])
        ->and($en['answer']['title'])->toBe($picture['en'])
        ->and($fr['answer']['title'])->toBe($picture['fr']);

    $item = bgItem('nonogram', $fr);
    $solution = $fr['answer']['solution'];
    $wrong = $solution;
    $wrong[0][0] = 1 - $wrong[0][0];

    expect($handler->check($item, ['grid' => $solution]))->toBeTrue()
        ->and($handler->check($item, ['grid' => $wrong]))->toBeFalse()
        ->and($handler->correctResponse($item))->toBe(['grid' => $solution, 'title' => $picture['fr']])
        ->and($handler->points($item))->toBe(3)
        ->and($handler->validateResponse($item, ['grid' => $solution]))->toBe([])
        ->and($handler->validateResponse($item, ['grid' => array_slice($solution, 0, 4)]))->toHaveKey('response.grid')
        ->and($handler->validateResponse($item, ['grid' => array_map(fn ($row) => [...$row, 0], $solution)]))->toHaveKey('response.grid')
        ->and($handler->validateResponse($item, ['grid' => array_fill(0, 5, [0, 1, 2, 0, 0])]))->toHaveKey('response.grid')
        ->and($handler->validateResponse($item, []))->toHaveKey('response.grid');
});

it('lays out spot-the-difference scenes that are fair to tap', function () {
    $handler = app(SpotDifference::class);
    $height = 1 / SpotDifferenceGenerator::ASPECT;

    foreach (range(1, 10) as $difficulty) {
        foreach (range(1, 30) as $n) {
            $payload = $handler->generate("s{$n}", $difficulty, [])['payload'];
            $objects = array_column($payload['objects'], null, 'id');

            expect($payload['objects'])->toHaveCount(SpotDifference::objectsFor($difficulty))
                ->and($payload['changes'])->toHaveCount(SpotDifference::changesFor($difficulty))
                ->and($payload['aspect'])->toBe(1.25);

            foreach ($payload['objects'] as $i => $a) {
                expect($a['x'] - $a['size'] / 2)->toBeGreaterThanOrEqual(0)->and($a['x'] + $a['size'] / 2)->toBeLessThanOrEqual(1)
                    ->and($a['y'] - $a['size'] / 2)->toBeGreaterThanOrEqual(0)->and($a['y'] + $a['size'] / 2)->toBeLessThanOrEqual($height);

                foreach (array_slice($payload['objects'], $i + 1) as $b) {
                    expect(hypot($a['x'] - $b['x'], $a['y'] - $b['y']))->toBeGreaterThanOrEqual(($a['size'] + $b['size']) / 2);
                }
            }

            foreach ($payload['changes'] as $i => $change) {
                $object = $objects[$change['object_id']];

                expect($object['size'])->toBeGreaterThanOrEqual(0.1);

                match ($change['change']) {
                    'missing' => expect($change['value'])->toBeNull(),
                    'flip' => expect($object['kind'])->toBeIn(ClanArt::ASYMMETRIC_PROPS)->and($change['value'])->toBeTrue(),
                    'color' => expect($object['kind'])->toBeIn(ClanArt::PROPS)->and($change['value'])->toBeIn(ClanArt::COLORS)->not->toBe($object['color']),
                    'size' => expect($change['value'])->toBeIn([round($object['size'] * 1.5, 3), round($object['size'] * 0.6, 3)]),
                };

                foreach (array_slice($payload['changes'], $i + 1) as $other) {
                    $otherObject = $objects[$other['object_id']];
                    expect(hypot($object['x'] - $otherObject['x'], $object['y'] - $otherObject['y']))
                        ->toBeGreaterThanOrEqual(SpotDifferenceGenerator::hitRadius($object, $change) + SpotDifferenceGenerator::hitRadius($otherObject, $other));
                }
            }

            expect($handler->check(bgItem('spot_difference', ['payload' => $payload, 'answer' => []]), ['taps' => bgCentreTaps($payload)]))->toBeTrue();
        }
    }

    expect(SpotDifference::changesFor(3))->toBe(3)->and(SpotDifference::changesFor(4))->toBe(5)->and(SpotDifference::changesFor(8))->toBe(7)
        ->and(SpotDifference::pointsForChanges(3))->toBe(2)->and(SpotDifference::pointsForChanges(5))->toBe(3)->and(SpotDifference::pointsForChanges(7))->toBe(5);
});

it('verifies spot-the-difference taps with hit circles, misses and the tap limit', function () {
    $handler = app(SpotDifference::class);
    $payload = [
        'aspect' => 1.25,
        'objects' => [
            ['id' => 'o1', 'kind' => 'tree', 'x' => 0.2, 'y' => 0.2, 'size' => 0.16, 'color' => 'green', 'flip' => false],
            ['id' => 'o2', 'kind' => 'fox', 'x' => 0.8, 'y' => 0.6, 'size' => 0.04, 'color' => 'orange', 'flip' => false],
            ['id' => 'o3', 'kind' => 'boat', 'x' => 0.5, 'y' => 0.4, 'size' => 0.1, 'color' => 'blue', 'flip' => false],
        ],
        'changes' => [
            ['id' => 'd1', 'object_id' => 'o1', 'change' => 'color', 'value' => 'red'],
            ['id' => 'd2', 'object_id' => 'o2', 'change' => 'missing', 'value' => null],
            ['id' => 'd3', 'object_id' => 'o3', 'change' => 'size', 'value' => 0.15],
        ],
        'max_misses' => 2,
    ];
    $item = bgItem('spot_difference', ['payload' => $payload, 'answer' => []]);
    $o1 = [0.2, 0.25];   // y 0.2 of the width = 0.25 of the height
    $o2 = [0.8, 0.75];
    $o3 = [0.5, 0.5];

    expect($handler->validate($payload, []))->toBe([])
        ->and($handler->check($item, ['taps' => [$o1, $o2, $o3]]))->toBeTrue()
        // radius 0.12 for o1 (0.16 × 0.75): just inside / outside
        ->and($handler->check($item, ['taps' => [[0.31, 0.25], $o2, $o3]]))->toBeTrue()
        ->and($handler->check($item, ['taps' => [[0.33, 0.25], $o2, $o3]]))->toBeFalse()
        ->and($handler->check($item, ['taps' => [[0.33, 0.25], $o1, $o2, $o3]]))->toBeTrue()
        // a tiny object still has the 0.06 minimum radius
        ->and($handler->check($item, ['taps' => [$o1, [0.85, 0.75], $o3]]))->toBeTrue()
        // a size change counts the bigger size: 0.15 × 0.75 = 0.1125
        ->and($handler->check($item, ['taps' => [$o1, $o2, [0.61, 0.5]]]))->toBeTrue()
        // repeated taps on a found change are neutral; taps after the last find are ignored
        ->and($handler->check($item, ['taps' => [$o1, $o1, $o1, $o1, $o2, $o3, [0.9, 0.1], [0.9, 0.1], [0.9, 0.1]]]))->toBeTrue()
        // 3 misses > max_misses 2
        ->and($handler->check($item, ['taps' => [[0.9, 0.1], [0.9, 0.1], [0.9, 0.1], $o1, $o2, $o3]]))->toBeFalse()
        ->and($handler->check($item, ['taps' => [[0.9, 0.1], [0.9, 0.1], $o1, $o2, $o3]]))->toBeTrue()
        // not every change found
        ->and($handler->check($item, ['taps' => [$o1, $o2]]))->toBeFalse()
        ->and($handler->check($item, ['taps' => []]))->toBeFalse()
        ->and($handler->correctResponse($item))->toBe(['changes' => ['d1', 'd2', 'd3']])
        ->and($handler->points($item))->toBe(2);

    // Tap y is normalized by the height: [0.8, 0.6] is at y = 0.48 of the width, 0.12 from o2 (radius 0.06).
    expect($handler->check($item, ['taps' => [$o1, [0.8, 0.6], $o3]]))->toBeFalse();

    // Overlapping circles: the nearest centre wins; a second tap on a found change finds nothing new.
    $overlap = bgItem('spot_difference', ['payload' => [
        'aspect' => 1.25,
        'objects' => [
            ['id' => 'o1', 'kind' => 'sun', 'x' => 0.3, 'y' => 0.4, 'size' => 0.12, 'color' => 'yellow', 'flip' => false],
            ['id' => 'o2', 'kind' => 'ball', 'x' => 0.45, 'y' => 0.4, 'size' => 0.12, 'color' => 'red', 'flip' => false],
        ],
        'changes' => [
            ['id' => 'd1', 'object_id' => 'o1', 'change' => 'missing', 'value' => null],
            ['id' => 'd2', 'object_id' => 'o2', 'change' => 'size', 'value' => 0.2],
        ],
        'max_misses' => 0,
    ], 'answer' => []]);

    expect($handler->check($overlap, ['taps' => [[0.36, 0.5], [0.39, 0.5]]]))->toBeTrue()
        ->and($handler->check($overlap, ['taps' => [[0.39, 0.5], [0.39, 0.5]]]))->toBeFalse()
        // the size change uses the bigger size: radius 0.2 × 0.75 = 0.15 around o2
        ->and($handler->check($overlap, ['taps' => [[0.3, 0.5], [0.59, 0.5]]]))->toBeTrue()
        ->and($handler->check($overlap, ['taps' => [[0.3, 0.5], [0.61, 0.5]]]))->toBeFalse();

    expect($handler->validateResponse($item, ['taps' => array_fill(0, 60, [0.5, 0.5])]))->toBe([]);

    foreach ([
        ['taps' => array_fill(0, 61, [0.5, 0.5])],
        ['taps' => [[0.5]]],
        ['taps' => [[0.5, 1.2]]],
        ['taps' => [['0.5', 0.5]]],
        ['taps' => 'x'],
        [],
    ] as $response) {
        expect($handler->validateResponse($item, $response))->toHaveKey('response.taps');
    }

    expect($handler->validate([...$payload, 'changes' => [['id' => 'd1', 'object_id' => 'o9', 'change' => 'missing', 'value' => null]]], []))->toHaveKey('payload.changes')
        ->and($handler->validate([...$payload, 'changes' => [['id' => 'd1', 'object_id' => 'o1', 'change' => 'color', 'value' => 'teal']]], []))->toHaveKey('payload.changes');
});

it('rejects options on brain game generators', function (string $type) {
    $handler = app('App\\Modules\\ChallengeTypes\\Handlers\\'.str_replace('_', '', ucwords($type, '_')));

    expect($handler->validateOptions([]))->toBe([])
        ->and($handler->validateOptions(['size' => 9]))->toHaveKey('options');
})->with('brain games');
