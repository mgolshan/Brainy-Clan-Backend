<?php

use App\Modules\ChallengeTypes\Generators\CrossMathGenerator;
use App\Modules\ChallengeTypes\Generators\CrossMathSolver;
use App\Modules\ChallengeTypes\Generators\MazeGenerator;
use App\Modules\ChallengeTypes\Handlers\CodeBreaker;
use App\Modules\ChallengeTypes\Handlers\CrossMath;
use App\Modules\ChallengeTypes\Handlers\Maze;
use App\Modules\ChallengeTypes\Support\ClanArt;
use App\Modules\Content\Models\ContentItem;

/**
 * Level-up puzzles (docs/API.md "Level-up puzzles"): Cross Math, Maze and Code Breaker generators
 * are deterministic, valid at every difficulty and fast; Cross Math blanks have a unique
 * arrangement, mazes are perfect with a shortest star route; the verifiers follow the contract
 * (wrong answer vs 422) and the points table.
 */
function lpItem(string $type, array $generated): ContentItem
{
    return (new ContentItem)->forceFill(['type_key' => $type, 'payload' => $generated['payload'], 'answer' => $generated['answer']]);
}

/**
 * A Cross Math payload + fills as the generator's grid shape (for the solver) and blank cells.
 *
 * @return array{0: array{n: int, numbers: list<list<int>>, row_ops: list<list<string>>, col_ops: list<list<string>>}, 1: list<array{0: int, 1: int}>}
 */
function lpCrossMathGrid(array $payload, array $fills): array
{
    $cells = $payload['cells'];

    foreach ($fills as [$r, $c, $value]) {
        $cells[$r][$c] = $value;
    }

    $size = $payload['rows'];
    $n = intdiv($size - 1, 2);
    $numbers = [];
    $rowOps = [];
    $colOps = [];

    for ($i = 0; $i <= $n; $i++) {
        for ($j = 0; $j <= $n; $j++) {
            $numbers[$i][$j] = $cells[2 * $i][2 * $j];
        }

        for ($t = 0; $t < $n - 1; $t++) {
            $rowOps[$i][] = $cells[2 * $i][2 * $t + 1];
            $colOps[$i][] = $cells[2 * $t + 1][2 * $i];
        }
    }

    return [['n' => $n, 'numbers' => $numbers, 'row_ops' => $rowOps, 'col_ops' => $colOps], array_map(fn (array $f) => [intdiv($f[0], 2), intdiv($f[1], 2)], $fills)];
}

/**
 * Distinct orderings of a multiset.
 *
 * @param  list<int>  $values
 * @return Generator<list<int>>
 */
function lpPermutations(array $values): Generator
{
    if ($values === []) {
        yield [];

        return;
    }

    foreach (array_unique($values) as $value) {
        $rest = $values;
        unset($rest[array_search($value, $rest, true)]);

        foreach (lpPermutations(array_values($rest)) as $tail) {
            yield [$value, ...$tail];
        }
    }
}

dataset('level-up puzzles', [
    'cross math' => [CrossMath::class, 'cross_math', 12],
    'maze' => [Maze::class, 'maze', 25],
    'code breaker' => [CodeBreaker::class, 'code_breaker', 25],
]);

it('generates the same item for the same seed and a valid item at every difficulty', function (string $class, string $type, int $seeds) {
    $handler = app($class);
    $payloads = [];

    foreach (range(1, 10) as $difficulty) {
        foreach (range(1, $seeds) as $n) {
            $item = $handler->generate("seed-{$n}", $difficulty, []);

            expect($handler->generate("seed-{$n}", $difficulty, ['locale' => 'fr']))->toBe($item)
                ->and($handler->validate($item['payload'], $item['answer']))->toBe([])
                ->and($handler->points(lpItem($type, $item)))->toBe($handler->pointsFor([], $difficulty))
                ->and($handler->forClient(lpItem($type, $item)))->toEqual($item['payload'])
                ->and($handler->check(lpItem($type, $item), $handler->correctResponse(lpItem($type, $item)) === ['guesses' => null, 'code' => $item['payload']['code'] ?? null]
                    ? ['guesses' => [$item['payload']['code']]]
                    : $handler->correctResponse(lpItem($type, $item))))->toBeTrue();

            $payloads[json_encode($item['payload'])] = true;
        }
    }

    expect(count($payloads))->toBeGreaterThan(9 * $seeds)
        ->and($handler->validateOptions([]))->toBe([])
        ->and($handler->validateOptions(['size' => 4]))->toHaveKey('options');
})->with('level-up puzzles');

it('keeps every cross math number and intermediate a whole number in range, with a unique blank arrangement', function () {
    $handler = app(CrossMath::class);
    $solver = new CrossMathSolver;
    $full = 0;
    $newest = 0;
    $total = 0;

    foreach (range(1, 10) as $difficulty) {
        [$n, $ops, $max, $blanks] = CrossMathGenerator::LEVELS[$difficulty];

        foreach (range(1, 30) as $seed) {
            $item = $handler->generate("cm-{$seed}", $difficulty, []);
            $payload = $item['payload'];
            $fills = $item['answer']['fills'];
            [$grid, $cells] = lpCrossMathGrid($payload, $fills);
            $total++;

            expect($payload['rows'])->toBe($difficulty <= 6 ? 5 : 7)->and($payload['cols'])->toBe($payload['rows'])
                ->and($payload['bank'])->toBe(collect($fills)->pluck(2)->sort()->values()->all())
                ->and(count($payload['bank']))->toBeGreaterThanOrEqual($n)->toBeLessThanOrEqual($blanks)
                ->and($item['answer']['difficulty'])->toBe($difficulty);

            // Every number 0..max; every equation valid under the strict rules (intermediates too).
            foreach ($grid['numbers'] as $row) {
                foreach ($row as $value) {
                    expect($value)->toBeInt()->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual($max);
                }
            }

            for ($line = 0; $line <= $n; $line++) {
                expect(array_diff([...$grid['row_ops'][$line], ...$grid['col_ops'][$line]], $ops))->toBe([])
                    ->and(CrossMathGenerator::evaluateStrict(array_slice($grid['numbers'][$line], 0, $n), $grid['row_ops'][$line], $max))->toBe($grid['numbers'][$line][$n])
                    ->and(CrossMathGenerator::evaluateStrict(array_slice(array_column($grid['numbers'], $line), 0, $n), $grid['col_ops'][$line], $max))->toBe($grid['numbers'][$n][$line]);
            }

            // Exactly one arrangement of the bank (exhaustive count, no budget).
            expect($solver->count($grid, $cells, 2, PHP_INT_MAX))->toBe(1);

            $full += count($payload['bank']) === $blanks ? 1 : 0;
            $newest += in_array($ops[count($ops) - 1], array_merge(...$grid['row_ops'], ...$grid['col_ops']), true) ? 1 : 0;
        }
    }

    // The fallbacks (fewer blanks, older operators) are rare.
    expect($full / $total)->toBeGreaterThan(0.9)->and($newest / $total)->toBeGreaterThan(0.85);
});

it('proves cross math uniqueness by brute force on small grids', function () {
    $handler = app(CrossMath::class);

    foreach (range(1, 6) as $difficulty) {
        foreach (range(1, 4) as $seed) {
            $item = $handler->generate("bf-{$seed}", $difficulty, []);
            $fills = $item['answer']['fills'];
            $correct = 0;

            foreach (lpPermutations($item['payload']['bank']) as $values) {
                $response = ['fills' => array_map(fn (array $fill, int $value) => [$fill[0], $fill[1], $value], $fills, $values)];
                $correct += $handler->check(lpItem('cross_math', $item), $response) ? 1 : 0;
            }

            expect($correct)->toBe(1);
        }
    }
});

it('generates cross math puzzles fast, with a hard bound', function () {
    $handler = app(CrossMath::class);
    $handler->generate('warm-up', 10, []);

    foreach ([5, 9, 10] as $difficulty) {
        $times = [];

        foreach (range(1, 40) as $seed) {
            $started = hrtime(true);
            $handler->generate("speed-{$seed}", $difficulty, []);
            $times[] = (hrtime(true) - $started) / 1e6;
        }

        expect(array_sum($times) / count($times))->toBeLessThan(50)
            ->and(max($times))->toBeLessThan(400);
    }

    expect(CrossMathGenerator::TOTAL_BUDGET)->toBeLessThanOrEqual(100000);
});

it('evaluates cross math equations with normal precedence and exact division', function () {
    expect(CrossMathSolver::evaluate([2, 3, 4], ['+', '*']))->toBe(14)
        ->and(CrossMathSolver::evaluate([2, 3, 4], ['*', '+']))->toBe(10)
        ->and(CrossMathSolver::evaluate([10, 4, 3], ['-', '-']))->toBe(3)
        ->and(CrossMathSolver::evaluate([12, 6, 2], ['/', '/']))->toBe(1)
        ->and(CrossMathSolver::evaluate([7, 2, 2], ['/', '*']))->toBe(7)
        ->and(CrossMathSolver::evaluate([1, 6, 3], ['+', '/']))->toBe(3)
        ->and(CrossMathSolver::evaluate([7, 2], ['/']))->toBeNull()
        ->and(CrossMathSolver::evaluate([5, 0], ['/']))->toBeNull()
        ->and(CrossMathGenerator::evaluateStrict([2, 5, 4], ['-', '+'], 20))->toBeNull()   // 2 − 5 < 0
        ->and(CrossMathGenerator::evaluateStrict([9, 1], ['*'], 20))->toBeNull()           // no × 1
        ->and(CrossMathGenerator::evaluateStrict([7, 2, 2], ['/', '*'], 20))->toBeNull()   // 7 ÷ 2 not whole
        ->and(CrossMathGenerator::evaluateStrict([6, 3, 5], ['*', '-'], 20))->toBe(13)
        ->and(CrossMathGenerator::evaluateStrict([6, 4, 5], ['*', '-'], 10))->toBeNull();  // 24 > max
});

it('checks cross math fills: any valid arrangement, wrong answers and malformed fills', function () {
    $handler = app(CrossMath::class);
    $payload = [
        'rows' => 5, 'cols' => 5,
        'cells' => [['?', '+', '?', '=', 3], ['+', null, '+', null, '+'], ['?', '+', '?', '=', 3], ['=', null, '=', null, '='], [3, '+', 3, '=', 6]],
        'bank' => [1, 1, 2, 2],
    ];
    $item = lpItem('cross_math', ['payload' => $payload, 'answer' => ['fills' => [[0, 0, 1], [0, 2, 2], [2, 0, 2], [2, 2, 1]], 'difficulty' => 2]]);

    expect($handler->validate($payload, $item->answer))->toBe([])
        ->and($handler->check($item, ['fills' => [[0, 0, 1], [0, 2, 2], [2, 0, 2], [2, 2, 1]]]))->toBeTrue()
        ->and($handler->check($item, ['fills' => [[2, 2, 2], [2, 0, 1], [0, 2, 1], [0, 0, 2]]]))->toBeTrue()   // the other arrangement, any order
        ->and($handler->validateResponse($item, ['fills' => [[0, 0, 1], [0, 2, 2]]]))->toBe([])
        ->and($handler->check($item, ['fills' => [[0, 0, 1], [0, 2, 2]]]))->toBeFalse()                    // fewer fills: wrong, not 422
        ->and($handler->check($item, ['fills' => []]))->toBeFalse()
        ->and($handler->check($item, ['fills' => [[0, 0, 1], [0, 2, 2], [2, 0, 1], [2, 2, 2]]]))->toBeFalse()   // equations fail
        ->and($handler->check($item, ['fills' => [[0, 0, 0], [0, 2, 3], [2, 0, 3], [2, 2, 0]]]))->toBeFalse()   // holds, but not the bank
        ->and($handler->check($item, ['fills' => [[0, 0, -1], [0, 2, 4], [2, 0, 4], [2, 2, -1]]]))->toBeFalse()
        ->and($handler->correctResponse($item))->toBe(['fills' => [[0, 0, 1], [0, 2, 2], [2, 0, 2], [2, 2, 1]]])
        ->and($handler->points($item))->toBe(2);

    foreach ([
        ['fills' => [[0, 4, 3]]],                                           // not a "?" cell
        ['fills' => [[0, 1, 3]]],                                           // an operator cell
        ['fills' => [[6, 0, 1]]],                                           // out of range
        ['fills' => [[-2, 0, 1]]],
        ['fills' => [[0, 0, 1], [0, 0, 2]]],                                // same cell twice
        ['fills' => [[0, 0, 1], [0, 2, 2], [2, 0, 2], [2, 2, 1], [4, 0, 3]]], // more fills than blanks
        ['fills' => [[0, 0, '1']]],                                         // not a whole number
        ['fills' => [[0, 0, 1.5]]],
        ['fills' => [[0, 0]]],
        ['fills' => 'oops'],
        ['fills' => ['a' => [0, 0, 1]]],
        [],
    ] as $response) {
        expect($handler->validateResponse($item, $response))->toHaveKey('response.fills');
    }

    // Points by difficulty, and from the grid when an item has no stored difficulty.
    expect(array_map(fn (int $d) => CrossMath::pointsForDifficulty($d), range(1, 10)))->toBe([2, 2, 2, 3, 3, 3, 5, 5, 8, 8])
        ->and($handler->points(lpItem('cross_math', ['payload' => $payload, 'answer' => []])))->toBe(2)
        ->and($handler->points(lpItem('cross_math', ['payload' => ['rows' => 7, 'bank' => range(1, 14)], 'answer' => []])))->toBe(8);

    // Admin validation.
    expect($handler->validate([...$payload, 'bank' => [2, 2, 1, 1]], $item->answer))->toHaveKey('payload.bank')
        ->and($handler->validate([...$payload, 'cells' => [[1, '+', 2]]], []))->toHaveKey('payload.cells')
        ->and($handler->validate([...$payload, 'cols' => 7], []))->toHaveKey('payload.cells')
        ->and($handler->validate($payload, ['fills' => [[0, 0, 2], [0, 2, 2], [2, 0, 1], [2, 2, 1]]]))->toHaveKey('answer.fills');
});

it('builds perfect mazes with the goal farthest away and a shortest route through every star', function () {
    $handler = app(Maze::class);

    foreach (range(1, 10) as $difficulty) {
        foreach (range(1, 30) as $seed) {
            $item = $handler->generate("mz-{$seed}", $difficulty, []);
            $payload = $item['payload'];
            $size = Maze::SIZES[$difficulty];
            $bfs = MazeGenerator::distances($payload['cells'], [0, 0]);
            $distance = fn (array $cell) => $bfs['dist'][$cell[0] * $size + $cell[1]];

            expect($payload['rows'])->toBe($size)->and($payload['cols'])->toBe($size)
                ->and($payload['start'])->toBe([0, 0])
                ->and($payload['stars'])->toHaveCount(Maze::STARS[$difficulty])
                ->and(count(array_unique(array_map('json_encode', $payload['stars']))))->toBe(count($payload['stars']))
                ->and($payload['stars'])->not->toContain($payload['start'])->not->toContain($payload['goal'])
                ->and($distance($payload['goal']))->toBe(max($bfs['dist']))
                ->and(count($bfs['dist']))->toBe($size * $size);

            // Neighbours agree and the border is walled (validate() checks it too, this is explicit).
            foreach ($payload['cells'] as $r => $row) {
                foreach ($row as $c => $walls) {
                    expect($walls)->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(15);

                    if ($c + 1 < $size) {
                        expect(($walls & 2) === 0)->toBe(($row[$c + 1] & 8) === 0);
                    }

                    if ($r + 1 < $size) {
                        expect(($walls & 4) === 0)->toBe(($payload['cells'][$r + 1][$c] & 1) === 0);
                    }
                }

                expect($row[0] & 8)->toBe(8)->and($row[$size - 1] & 2)->toBe(2);
            }

            expect(array_sum(array_map(fn (int $w) => $w & 1, $payload['cells'][0])))->toBe($size)
                ->and(array_sum(array_map(fn (int $w) => ($w & 4) >> 2, $payload['cells'][$size - 1])))->toBe($size);

            // In a tree the shortest walk start → all stars → goal crosses the Steiner tree of
            // those cells twice except along the start–goal route: 2E − d(start, goal) steps.
            $steiner = [];

            foreach ([...$payload['stars'], $payload['goal']] as $target) {
                $index = $target[0] * $size + $target[1];

                while ($index !== -1) {
                    $steiner[$index] = true;
                    $index = $bfs['parent'][$index];
                }
            }

            $path = $item['answer']['path'];
            expect(count($path) - 1)->toBe(2 * (count($steiner) - 1) - $distance($payload['goal']))
                ->and($handler->validateResponse(lpItem('maze', $item), ['path' => $path]))->toBe([])
                ->and($handler->check(lpItem('maze', $item), ['path' => $path]))->toBeTrue()
                ->and(count($path))->toBeLessThanOrEqual($size * $size * 2);
        }
    }

    expect(array_map(fn (int $d) => Maze::pointsForSize(Maze::SIZES[$d]), range(1, 10)))->toBe([1, 1, 1, 2, 2, 2, 3, 3, 5, 5]);
});

it('replays maze paths: wrong answers and malformed paths', function () {
    $handler = app(Maze::class);
    // A 3×3 snake: (0,0) → (0,1) → (0,2) → (1,2) → (1,1) → (1,0) → (2,0) → (2,1) → (2,2).
    $payload = ['rows' => 3, 'cols' => 3, 'cells' => [[13, 5, 3], [9, 5, 6], [12, 5, 7]], 'start' => [0, 0], 'goal' => [2, 2], 'stars' => [[1, 1]]];
    $snake = [[0, 0], [0, 1], [0, 2], [1, 2], [1, 1], [1, 0], [2, 0], [2, 1], [2, 2]];
    $item = lpItem('maze', ['payload' => $payload, 'answer' => ['path' => $snake]]);

    expect($handler->validate($payload, ['path' => $snake]))->toBe([])
        ->and(MazeGenerator::shortestTour($payload['cells'], [0, 0], [2, 2], [[1, 1]]))->toBe($snake)
        ->and($handler->check($item, ['path' => $snake]))->toBeTrue()
        ->and($handler->check($item, ['path' => [[0, 0], [0, 1], [0, 0], [0, 1], ...array_slice($snake, 2)]]))->toBeTrue()  // revisits allowed
        ->and($handler->check($item, ['path' => array_slice($snake, 0, 5)]))->toBeFalse()                                // stops early
        ->and($handler->check($item, ['path' => [[0, 0]]]))->toBeFalse()
        ->and($handler->correctResponse($item))->toBe(['path' => $snake])
        ->and($handler->points($item))->toBe(1);

    $noStar = lpItem('maze', ['payload' => [...$payload, 'stars' => [[0, 2]]], 'answer' => []]);
    expect($handler->check($noStar, ['path' => $snake]))->toBeTrue();

    foreach ([
        ['path' => [[0, 1], [0, 2]]],                   // not starting on start
        ['path' => [[0, 0], [1, 0]]],                   // through a wall
        ['path' => [[0, 0], [0, 2]]],                   // not a neighbour
        ['path' => [[0, 0], [0, 0]]],                   // standing still
        ['path' => [[0, 0], [0, 1], [-1, 1]]],          // out of range
        ['path' => [[0, 0], [0, 3]]],
        ['path' => array_merge(...array_fill(0, 19, [[0, 0], [0, 1]]))], // 38 > 3 × 3 × 4
        ['path' => [[0, 0], [0, '1']]],
        ['path' => [[0, 0, 1]]],
        ['path' => []],
        ['path' => 'oops'],
        [],
    ] as $response) {
        expect($handler->validateResponse($item, $response))->toHaveKey('response.path');
    }

    expect($handler->validateResponse($item, ['path' => array_merge(...array_fill(0, 18, [[0, 0], [0, 1]]))]))->toBe([]);

    // Admin validation: imperfect mazes, bad stars, a path that misses a star.
    expect($handler->validate([...$payload, 'cells' => [[13, 5, 3], [9, 4, 6], [12, 5, 7]]], ['path' => $snake]))->toHaveKey('payload.cells')  // loop
        ->and($handler->validate([...$payload, 'cells' => [[12, 5, 3], [9, 5, 6], [12, 5, 7]]], ['path' => $snake]))->toHaveKey('payload.cells') // open border
        ->and($handler->validate([...$payload, 'stars' => [[0, 0]]], ['path' => $snake]))->toHaveKey('payload.stars')
        ->and($handler->validate([...$payload, 'stars' => [[1, 1], [1, 1]]], ['path' => $snake]))->toHaveKey('payload.stars')
        ->and($handler->validate($payload, ['path' => [[0, 0], [0, 1], [0, 2], [1, 2], [1, 1]]]))->toHaveKey('answer.path');
});

it('sets up code breaker levels per the table with Clan kid pegs', function () {
    $handler = app(CodeBreaker::class);
    $kids = ClanArt::kidAvatars();

    foreach (range(1, 10) as $difficulty) {
        [$length, $pegCount, $repeats, $maxGuesses] = CodeBreaker::LEVELS[$difficulty];
        $sawRepeat = false;

        foreach (range(1, 40) as $seed) {
            $payload = $handler->generate("cb-{$seed}", $difficulty, [])['payload'];

            expect($payload['pegs'])->toHaveCount($pegCount)
                ->and(array_unique($payload['pegs']))->toHaveCount($pegCount)
                ->and(array_diff($payload['pegs'], $kids))->toBe([])
                ->and($payload['length'])->toBe($length)
                ->and($payload['repeats'])->toBe($repeats)
                ->and($payload['max_guesses'])->toBe($maxGuesses)
                ->and($payload['code'])->toHaveCount($length)
                ->and(max($payload['code']))->toBeLessThan($pegCount)
                ->and(min($payload['code']))->toBeGreaterThanOrEqual(0);

            if (! $repeats) {
                expect(array_unique($payload['code']))->toHaveCount($length);
            }

            $sawRepeat = $sawRepeat || count(array_unique($payload['code'])) < $length;
        }

        expect($sawRepeat)->toBe($repeats);
    }

    expect(array_map(fn (int $d) => $handler->pointsFor([], $d), range(1, 10)))->toBe([2, 2, 3, 3, 3, 3, 5, 5, 5, 5]);
});

it('scores code breaker guesses like Mastermind and replays them', function () {
    expect(CodeBreaker::feedback([0, 2, 1, 5], [0, 1, 2, 3]))->toBe(['exact' => 1, 'near' => 2])
        ->and(CodeBreaker::feedback([1, 2, 1, 1], [1, 1, 2, 2]))->toBe(['exact' => 1, 'near' => 2])
        ->and(CodeBreaker::feedback([3, 3, 3], [0, 1, 2]))->toBe(['exact' => 0, 'near' => 0])
        ->and(CodeBreaker::feedback([2, 0, 3], [2, 0, 3]))->toBe(['exact' => 3, 'near' => 0]);

    $handler = app(CodeBreaker::class);
    $payload = ['pegs' => ['fox', 'owl', 'frog', 'panda'], 'length' => 3, 'repeats' => false, 'max_guesses' => 4, 'code' => [2, 0, 3]];
    $item = lpItem('code_breaker', ['payload' => $payload, 'answer' => []]);

    expect($handler->validate($payload, []))->toBe([])
        ->and($handler->check($item, ['guesses' => [[0, 1, 2], [1, 1, 1], [2, 0, 3]]]))->toBeTrue()   // repeats allowed in a guess
        ->and($handler->check($item, ['guesses' => [[2, 0, 3]]]))->toBeTrue()
        ->and($handler->check($item, ['guesses' => [[0, 1, 2], [2, 0, 1]]]))->toBeFalse()           // not solved
        ->and($handler->check($item, ['guesses' => []]))->toBeFalse()
        ->and($handler->validateResponse($item, ['guesses' => []]))->toBe([])
        ->and($handler->correctResponse($item))->toBe(['guesses' => null, 'code' => [2, 0, 3]])
        ->and($handler->points($item))->toBe(2);

    foreach ([
        ['guesses' => [[0, 1]]],                                        // wrong length
        ['guesses' => [[0, 1, 4]]],                                     // peg out of range
        ['guesses' => [[0, 1, -1]]],
        ['guesses' => [[0, 1, '2']]],
        ['guesses' => array_fill(0, 5, [0, 1, 2])],                     // more than max_guesses
        ['guesses' => [[2, 0, 3], [0, 1, 2]]],                          // a guess after the match
        ['guesses' => 'oops'],
        [],
    ] as $response) {
        expect($handler->validateResponse($item, $response))->toHaveKey('response.guesses');
    }

    expect($handler->validate([...$payload, 'code' => [1, 1, 2]], []))->toHaveKey('payload.code')   // repeats when not allowed
        ->and($handler->validate([...$payload, 'repeats' => true, 'code' => [1, 1, 2]], []))->toBe([])
        ->and($handler->validate([...$payload, 'pegs' => ['fox', 'fox-teen', 'owl', 'frog']], []))->toHaveKey('payload.pegs')
        ->and($handler->validate([...$payload, 'length' => 6], []))->toHaveKey('payload.length');
});
