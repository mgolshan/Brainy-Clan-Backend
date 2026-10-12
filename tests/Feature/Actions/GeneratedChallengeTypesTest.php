<?php

use App\Modules\ChallengeTypes\Generators\MathSprintGenerator;
use App\Modules\ChallengeTypes\Generators\SudokuGenerator;
use App\Modules\ChallengeTypes\Generators\WordSearchBuilder;
use App\Modules\ChallengeTypes\Handlers\MathSprint;
use App\Modules\ChallengeTypes\Handlers\Sudoku;
use App\Modules\ChallengeTypes\Handlers\WordPuzzle;
use App\Modules\ChallengeTypes\Support\GeneratorSpec;
use App\Modules\ChallengeTypes\Support\Rational;
use App\Modules\Content\Models\ContentItem;
use App\Modules\Play\Actions\AddSessionItems;

/**
 * B7 challenge types: generator properties and each handler's validateResponse / check / points.
 */
function contentOf(string $type, array $generated): ContentItem
{
    return (new ContentItem)->forceFill(['type_key' => $type, 'payload' => $generated['payload'], 'answer' => $generated['answer']]);
}

/**
 * @param  list<list<int>>  $grid
 */
function givensIn(array $grid): int
{
    return count(array_filter(array_merge(...$grid)));
}

it('generates sudokus with a unique, valid solution and the target number of givens', function (int $size, int $difficulty) {
    $generator = new SudokuGenerator;
    $target = Sudoku::givensFor($size, $difficulty);

    foreach (range(1, 8) as $n) {
        $puzzle = $generator->generate("seed-{$n}", $size, $target);

        expect($puzzle['grid'])->toHaveCount($size)
            ->and($generator->isValidSolution($puzzle['solution']))->toBeTrue()
            ->and($generator->countSolutions($puzzle['grid']))->toBe(1)
            ->and(givensIn($puzzle['grid']))->toBe($target)
            ->and($puzzle['givens'])->toBe($target);

        foreach ($puzzle['grid'] as $r => $row) {
            foreach ($row as $c => $value) {
                if ($value !== 0) {
                    expect($puzzle['solution'][$r][$c])->toBe($value);
                }
            }
        }
    }
})->with([[4, 1], [4, 3], [6, 3], [6, 6], [9, 5], [9, 10]]);

it('scales sudoku givens with difficulty', function () {
    expect(Sudoku::givensFor(4, 1))->toBe(10)->and(Sudoku::givensFor(4, 3))->toBe(6)
        ->and(Sudoku::givensFor(6, 3))->toBe(22)->and(Sudoku::givensFor(6, 6))->toBe(12)
        ->and(Sudoku::givensFor(9, 5))->toBe(46)->and(Sudoku::givensFor(9, 10))->toBe(28)
        ->and(Sudoku::givensFor(9, 1))->toBe(46)
        ->and(Sudoku::givensFor(9, 8))->toBeLessThan(Sudoku::givensFor(9, 6));
});

it('generates the same sudoku for the same seed and different ones for other seeds', function () {
    $sudoku = app(Sudoku::class);

    expect($sudoku->generate('abc', 7, ['size' => 9]))->toBe($sudoku->generate('abc', 7, ['size' => 9]))
        ->and($sudoku->generate('abc', 7, ['size' => 9]))->not->toBe($sudoku->generate('abd', 7, ['size' => 9]));
});

it('generates a 9x9 sudoku fast enough for request time', function () {
    $sudoku = app(Sudoku::class);
    $start = hrtime(true);

    foreach (range(1, 10) as $n) {
        $sudoku->generate("speed-{$n}", 10, ['size' => 9]);
    }

    expect((hrtime(true) - $start) / 1e6 / 10)->toBeLessThan(200);
});

it('checks a sudoku: keeps givens, valid complete grid, 422 for malformed grids', function () {
    $sudoku = app(Sudoku::class);
    $item = contentOf('sudoku', $sudoku->generate('check', 2, ['size' => 4]));
    $solution = $item->answer['solution'];

    expect($sudoku->forClient($item))->toBe(['size' => 4, 'box' => [2, 2], 'grid' => $item->payload['grid']])
        ->and(json_encode($sudoku->forClient($item)))->not->toContain('solution')
        ->and($sudoku->validateResponse($item, ['grid' => $solution]))->toBe([])
        ->and($sudoku->check($item, ['grid' => $solution]))->toBeTrue()
        ->and($sudoku->correctResponse($item))->toBe(['grid' => $solution])
        ->and($sudoku->points($item))->toBe(3);

    // Incomplete (the puzzle itself) and a broken solution are mistakes, not 422s.
    $broken = $solution;
    [$broken[0][0], $broken[0][1]] = [$broken[0][1], $broken[0][0]];
    expect($sudoku->validateResponse($item, ['grid' => $item->payload['grid']]))->toBe([])
        ->and($sudoku->check($item, ['grid' => $item->payload['grid']]))->toBeFalse()
        ->and($sudoku->check($item, ['grid' => $broken]))->toBeFalse();

    // Malformed shapes are 422.
    expect($sudoku->validateResponse($item, ['grid' => [[1, 2, 3, 4]]]))->toHaveKey('response.grid')
        ->and($sudoku->validateResponse($item, ['grid' => array_fill(0, 4, [1, 2, 3, 5])]))->toHaveKey('response.grid')
        ->and($sudoku->validateResponse($item, ['grid' => array_fill(0, 4, ['1', '2', '3', '4'])]))->toHaveKey('response.grid')
        ->and($sudoku->validateResponse($item, []))->toHaveKey('response.grid');
});

it('accepts any valid solution that keeps the givens', function () {
    $sudoku = app(Sudoku::class);
    // An empty 4x4 grid: many solutions; any valid one counts.
    $item = (new ContentItem)->forceFill(['type_key' => 'sudoku', 'payload' => ['size' => 4, 'box' => [2, 2], 'grid' => array_fill(0, 4, [0, 0, 0, 0])], 'answer' => ['solution' => [[1, 2, 3, 4], [3, 4, 1, 2], [2, 1, 4, 3], [4, 3, 2, 1]]]]);

    expect($sudoku->check($item, ['grid' => [[2, 1, 4, 3], [4, 3, 2, 1], [1, 2, 3, 4], [3, 4, 1, 2]]]))->toBeTrue();
});

it('validates sudoku content and points by size', function () {
    $sudoku = app(Sudoku::class);
    $nine = $sudoku->generate('v', 6, ['size' => 9]);
    $six = $sudoku->generate('v', 4, ['size' => 6]);

    expect($sudoku->validate($nine['payload'], $nine['answer']))->toBe([])
        ->and($sudoku->points(contentOf('sudoku', $nine)))->toBe(8)
        ->and($sudoku->points(contentOf('sudoku', $six)))->toBe(5)
        ->and($sudoku->pointsForOptions(['size' => 9]))->toBe(8)
        ->and($sudoku->validate(['size' => 5], []))->toHaveKey('payload.size')
        ->and($sudoku->validate([...$nine['payload'], 'grid' => array_fill(0, 9, array_fill(0, 9, 0))], $nine['answer']))->toHaveKey('payload.grid')
        ->and($sudoku->validateOptions(['size' => 7]))->toHaveKey('options.size');
});

it('compares math answers as exact numbers', function () {
    $parse = fn (string $v) => Rational::parse($v)['value'] ?? null;

    expect($parse('3/4')->equals($parse('6/8')))->toBeTrue()
        ->and($parse('0.75')->equals($parse('3/4')))->toBeTrue()
        ->and($parse('1 1/4')->equals($parse('5/4')))->toBeTrue()
        ->and($parse('0,5')->equals($parse('1/2')))->toBeTrue()
        ->and($parse('-13')->numerator)->toBe(-13)
        ->and(Rational::parse('abc'))->toBeNull()
        ->and(Rational::parse('3/0'))->toBeNull()
        ->and(Rational::parse(''))->toBeNull()
        ->and((new Rational(6, 8))->toFractionString())->toBe('3/4')
        ->and((new Rational(5, 4))->toDecimalString())->toBe('1.25')
        ->and((new Rational(1, 3))->isTerminatingDecimal())->toBeFalse();
});

it('generates math questions deterministically with consistent answers for every mode and difficulty', function () {
    $math = app(MathSprint::class);

    foreach (MathSprintGenerator::MODES as $mode) {
        foreach (range(1, 10) as $difficulty) {
            foreach (range(1, 5) as $n) {
                $item = $math->generate("m{$n}", $difficulty, ['mode' => $mode]);

                expect($item)->toBe($math->generate("m{$n}", $difficulty, ['mode' => $mode]))
                    ->and($math->validate($item['payload'], $item['answer']))->toBe([])
                    ->and($item['payload']['time_limit_ms'])->toBeInt()
                    ->and($math->check(contentOf('math_sprint', $item), ['value' => $item['answer']['value']]))->toBeTrue();
            }
        }
    }
});

it('generates the expected kinds of math questions', function () {
    $generator = new MathSprintGenerator;

    $easy = $generator->generate('x', 'times_tables', 1);
    $fraction = $generator->generate('x', 'fractions', 6);
    $decimal = $generator->generate('x', 'mental', 7);

    expect($easy['question'])->toMatch('/^[1-5] × [1-5]$/')
        ->and($easy['answer_format'])->toBe('integer')
        ->and($fraction['answer_format'])->toBe('fraction')
        ->and($fraction['question'])->toContain('/')
        ->and($decimal['answer_format'])->toBe('decimal');
});

it('accepts equivalent math answers and rejects malformed ones', function () {
    $math = app(MathSprint::class);
    $fraction = (new ContentItem)->forceFill(['type_key' => 'math_sprint', 'payload' => ['question' => '1/4 + 1/2', 'answer_format' => 'fraction', 'time_limit_ms' => 30000], 'answer' => ['value' => '3/4', 'allow_decimal' => true]]);
    $noDecimal = (new ContentItem)->forceFill(['type_key' => 'math_sprint', 'payload' => ['question' => '1/3 + 1/3', 'answer_format' => 'fraction', 'time_limit_ms' => null], 'answer' => ['value' => '2/3', 'allow_decimal' => false]]);
    $integer = (new ContentItem)->forceFill(['type_key' => 'math_sprint', 'payload' => ['question' => '7 × 8', 'answer_format' => 'integer', 'time_limit_ms' => 10000], 'answer' => ['value' => '56', 'allow_decimal' => true]]);

    expect($math->check($fraction, ['value' => '6/8']))->toBeTrue()
        ->and($math->check($fraction, ['value' => '0.75']))->toBeTrue()
        ->and($math->check($fraction, ['value' => '3/5']))->toBeFalse()
        ->and($math->check($noDecimal, ['value' => '4/6']))->toBeTrue()
        ->and($math->check($noDecimal, ['value' => '0.666667']))->toBeFalse()
        ->and($math->check($integer, ['value' => ' 56 ']))->toBeTrue()
        ->and($math->check($integer, ['value' => 56]))->toBeTrue()
        ->and($math->check($integer, ['value' => '57']))->toBeFalse()
        ->and($math->validateResponse($integer, ['value' => 'fifty']))->toHaveKey('response.value')
        ->and($math->validateResponse($integer, []))->toHaveKey('response.value')
        ->and($math->validateResponse($integer, ['value' => '56']))->toBe([])
        ->and($math->forClient($integer))->toBe(['question' => '7 × 8', 'answer_format' => 'integer', 'time_limit_ms' => 10000])
        ->and($math->correctResponse($fraction))->toBe(['value' => '3/4'])
        ->and($math->points($integer))->toBe(1);
});

it('builds word searches deterministically with every word placed', function () {
    $builder = new WordSearchBuilder;
    $words = ['ELEPHANT', 'GIRAFFE', 'PENGUIN', 'DOLPHIN', 'CHEETAH', 'OCTOPUS', 'FLAMINGO'];
    $built = $builder->build($words, 10, WordSearchBuilder::LEVELS['hard'], 'seed');

    expect($built)->toBe($builder->build($words, 10, WordSearchBuilder::LEVELS['hard'], 'seed'))
        ->and($built['grid'])->toHaveCount(10)
        ->and($built['found'])->toHaveCount(7);

    foreach ($built['found'] as $placement) {
        expect(WordSearchBuilder::spells($built['grid'], $placement['word'], $placement['start'], $placement['end']))->toBeTrue()
            ->and(WordSearchBuilder::spells($built['grid'], $placement['word'], $placement['end'], $placement['start']))->toBeTrue();
    }
});

it('checks word puzzles of every variant', function () {
    $handler = new WordPuzzle;
    $anagram = (new ContentItem)->forceFill(['type_key' => 'word_puzzle', 'payload' => ['variant' => 'anagram', 'letters' => ['T', 'A', 'C'], 'hint' => 'An animal'], 'answer' => ['words' => ['CAT', 'ACT']]]);
    $missing = (new ContentItem)->forceFill(['type_key' => 'word_puzzle', 'payload' => ['variant' => 'missing_letter', 'pattern' => 'EL_PHANT', 'hint' => null], 'answer' => ['word' => 'ELEPHANT']]);
    $built = (new WordSearchBuilder)->build(['CAT', 'DOG'], 6, WordSearchBuilder::LEVELS['easy'], 's');
    $search = (new ContentItem)->forceFill(['type_key' => 'word_puzzle', 'payload' => ['variant' => 'word_search', 'grid' => $built['grid'], 'words' => ['CAT', 'DOG']], 'answer' => ['found' => $built['found']]]);

    expect($handler->validate($anagram->payload, $anagram->answer))->toBe([])
        ->and($handler->validate($missing->payload, $missing->answer))->toBe([])
        ->and($handler->validate($search->payload, $search->answer))->toBe([])
        ->and($handler->validate(['variant' => 'anagram', 'letters' => ['T', 'A', 'C']], ['words' => ['DOG']]))->toHaveKey('answer.words')
        ->and($handler->validate(['variant' => 'missing_letter', 'pattern' => 'EL_PHANT'], ['word' => 'ELEPHANTS']))->toHaveKey('answer.word')
        ->and($handler->validate(['variant' => 'riddle'], []))->toHaveKey('payload.variant');

    expect($handler->forClient($anagram))->toBe(['variant' => 'anagram', 'letters' => ['T', 'A', 'C'], 'hint' => 'An animal'])
        ->and($handler->check($anagram, ['word' => 'cat']))->toBeTrue()
        ->and($handler->check($anagram, ['word' => 'act']))->toBeTrue()
        ->and($handler->check($anagram, ['word' => 'dog']))->toBeFalse()
        ->and($handler->correctResponse($anagram))->toBe(['word' => 'CAT'])
        ->and($handler->validateResponse($anagram, ['word' => '42']))->toHaveKey('response.word')
        ->and($handler->points($anagram))->toBe(1);

    expect($handler->check($missing, ['word' => 'Elephant ']))->toBeTrue()
        ->and($handler->check($missing, ['word' => 'elegant']))->toBeFalse()
        ->and($handler->correctResponse($missing))->toBe(['word' => 'ELEPHANT'])
        ->and($handler->points($missing))->toBe(1);

    $reversed = array_map(fn ($f) => ['word' => strtolower($f['word']), 'start' => $f['end'], 'end' => $f['start']], $built['found']);

    expect($handler->forClient($search))->toBe(['variant' => 'word_search', 'grid' => $built['grid'], 'words' => ['CAT', 'DOG']])
        ->and($handler->validateResponse($search, ['found' => $built['found']]))->toBe([])
        ->and($handler->check($search, ['found' => $built['found']]))->toBeTrue()
        ->and($handler->check($search, ['found' => $reversed]))->toBeTrue()
        ->and($handler->check($search, ['found' => [$built['found'][0]]]))->toBeFalse()
        ->and($handler->check($search, ['found' => [['word' => 'CAT', 'start' => [0, 0], 'end' => [5, 5]], $built['found'][1]]]))->toBe(WordSearchBuilder::spells($built['grid'], 'CAT', [0, 0], [5, 5]))
        ->and($handler->validateResponse($search, ['found' => [['word' => 'CAT', 'start' => [0, 9], 'end' => [0, 2]]]]))->toHaveKey('response.found.0')
        ->and($handler->validateResponse($search, ['found' => [['word' => 'COW', 'start' => [0, 0], 'end' => [0, 2]]]]))->toHaveKey('response.found.0.word')
        ->and($handler->validateResponse($search, ['found' => 'CAT']))->toHaveKey('response.found')
        ->and($handler->correctResponse($search))->toBe(['found' => $built['found']])
        ->and($handler->points($search))->toBe(3);
});

it('imports word puzzles and math from flat CSV rows', function () {
    $handler = new WordPuzzle;
    $anagram = $handler->fromFlatRow(['variant' => 'anagram', 'word' => 'listen', 'hint' => 'Use your ears', 'also_accepted' => 'silent']);
    $search = $handler->fromFlatRow(['variant' => 'word_search', 'words' => 'cat, dog, cow', 'size' => '6']);

    expect($handler->validate($anagram['payload'], $anagram['answer']))->toBe([])
        ->and($anagram['answer']['words'])->toBe(['LISTEN', 'SILENT'])
        ->and($handler->validate($search['payload'], $search['answer']))->toBe([])
        ->and(app(MathSprint::class)->fromFlatRow(['question' => '6 × 7', 'answer' => '42'])['answer'])->toBe(['value' => '42', 'allow_decimal' => false]);
});

it('keeps the target reachable within the blunder cap for mixed point values', function () {
    expect(AddSessionItems::isReachable(array_fill(0, 13, 1), 10, 3))->toBeTrue()
        ->and(AddSessionItems::isReachable(array_fill(0, 12, 1), 10, 3))->toBeFalse()
        ->and(AddSessionItems::isReachable([8, 8, 8, 8, 8], 10, 3))->toBeTrue()
        ->and(AddSessionItems::isReachable([8, 8, 8, 8], 10, 3))->toBeFalse()
        ->and(AddSessionItems::isReachable([8, 1, 1, 1, 1, 1], 5, 2))->toBeFalse()
        ->and(AddSessionItems::isReachable([], 0, 3))->toBeTrue();
});

it('resolves generator specs and difficulty ranges', function () {
    $spec = GeneratorSpec::fromArray(5, ['type' => 'sudoku', 'options' => ['size' => 9], 'min_difficulty' => 5, 'max_difficulty' => 6]);

    expect($spec->distanceTo(1, 4))->toBe(1)
        ->and($spec->distanceTo(1, 10))->toBe(0)
        ->and($spec->rangeFor(1, 10))->toBe([5, 6])
        ->and($spec->rangeFor(1, 4))->toBe([5, 5])
        ->and($spec->rangeFor(8, 10))->toBe([6, 6])
        ->and(GeneratorSpec::fromArray(1, ['options' => []]))->toBeNull();
});

it('compares words case-insensitively and Unicode-aware (NFC, French accents)', function () {
    $handler = new WordPuzzle;
    $decomposed = "e\u{0301}le\u{0300}ve"; // "élève" typed with combining accents
    $anagram = (new ContentItem)->forceFill(['type_key' => 'word_puzzle', 'payload' => ['variant' => 'anagram', 'letters' => ['V', 'È', 'L', 'E', 'É'], 'hint' => null], 'answer' => ['words' => ['ÉLÈVE']]]);
    $missing = (new ContentItem)->forceFill(['type_key' => 'word_puzzle', 'payload' => ['variant' => 'missing_letter', 'pattern' => 'ÉL_VE', 'hint' => null], 'answer' => ['word' => 'ÉLÈVE']]);

    expect($handler->validate($anagram->payload, $anagram->answer))->toBe([])
        ->and($handler->validate($missing->payload, $missing->answer))->toBe([])
        ->and($handler->check($anagram, ['word' => 'élève']))->toBeTrue()
        ->and($handler->check($anagram, ['word' => $decomposed]))->toBeTrue()
        ->and($handler->check($anagram, ['word' => 'eleve']))->toBeFalse()
        ->and($handler->validateResponse($missing, ['word' => $decomposed]))->toBe([])
        ->and($handler->check($missing, ['word' => 'ÉlÈvE']))->toBeTrue()
        ->and($handler->check($missing, ['word' => $decomposed]))->toBeTrue()
        ->and($handler->correctResponse($missing))->toBe(['word' => 'ÉLÈVE']);

    $built = (new WordSearchBuilder)->build(['élève', 'forêt', 'château'], 8, WordSearchBuilder::LEVELS['hard'], 'fr');
    $search = (new ContentItem)->forceFill(['type_key' => 'word_puzzle', 'payload' => ['variant' => 'word_search', 'grid' => $built['grid'], 'words' => array_column($built['found'], 'word')], 'answer' => ['found' => $built['found']]]);
    $lowercase = array_map(fn ($f) => [...$f, 'word' => mb_strtolower($f['word'])], $built['found']);

    expect(array_column($built['found'], 'word'))->toContain('ÉLÈVE')
        ->and($handler->validate($search->payload, $search->answer))->toBe([])
        ->and($handler->validateResponse($search, ['found' => $lowercase]))->toBe([])
        ->and($handler->check($search, ['found' => $lowercase]))->toBeTrue();
});

it('accepts word-search placements with start and end in either order', function () {
    $handler = new WordPuzzle;
    $built = (new WordSearchBuilder)->build(['CAT', 'DOG', 'HORSE'], 7, WordSearchBuilder::LEVELS['hard'], 'order');
    $search = (new ContentItem)->forceFill(['type_key' => 'word_puzzle', 'payload' => ['variant' => 'word_search', 'grid' => $built['grid'], 'words' => ['CAT', 'DOG', 'HORSE']], 'answer' => ['found' => $built['found']]]);
    $mixed = array_map(fn ($f, $i) => $i % 2 === 0 ? $f : [...$f, 'start' => $f['end'], 'end' => $f['start']], $built['found'], array_keys($built['found']));

    expect($handler->check($search, ['found' => $mixed]))->toBeTrue();
});

it('accepts negative math answers in every format', function () {
    $math = app(MathSprint::class);
    $item = fn (string $value, string $format) => (new ContentItem)->forceFill(['type_key' => 'math_sprint', 'payload' => ['question' => 'q', 'answer_format' => $format, 'time_limit_ms' => null], 'answer' => ['value' => $value, 'allow_decimal' => true]]);

    expect($math->check($item('-12', 'integer'), ['value' => '-12']))->toBeTrue()
        ->and($math->check($item('-12', 'integer'), ['value' => '12']))->toBeFalse()
        ->and($math->check($item('-0.5', 'decimal'), ['value' => '-0.5']))->toBeTrue()
        ->and($math->check($item('-0.5', 'decimal'), ['value' => '-1/2']))->toBeTrue()
        ->and($math->check($item('-3/4', 'fraction'), ['value' => '-3/4']))->toBeTrue()
        ->and($math->check($item('-3/4', 'fraction'), ['value' => '-6/8']))->toBeTrue()
        ->and($math->check($item('-3/4', 'fraction'), ['value' => '−0.75']))->toBeTrue()
        ->and($math->validateResponse($item('-3/4', 'fraction'), ['value' => '-3/4']))->toBe([]);
});
