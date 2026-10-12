<?php

namespace App\Modules\ChallengeTypes\Generators;

use App\Modules\ChallengeTypes\Support\Words;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;
use RuntimeException;

/**
 * Builds a word search grid deterministically from a seed (at seed/import time; the grid is
 * stored in the item's payload and the placements in its answer).
 */
class WordSearchBuilder
{
    /** [row step, col step] per direction. */
    public const array DIRECTIONS = [
        'right' => [0, 1], 'down' => [1, 0], 'down_right' => [1, 1], 'up_right' => [-1, 1],
        'left' => [0, -1], 'up' => [-1, 0], 'up_left' => [-1, -1], 'down_left' => [1, -1],
    ];

    /** Direction sets by level: easy reads left-to-right and top-to-bottom only. */
    public const array LEVELS = [
        'easy' => ['right', 'down'],
        'medium' => ['right', 'down', 'down_right', 'up_right'],
        'hard' => ['right', 'down', 'down_right', 'up_right', 'left', 'up', 'up_left', 'down_left'],
    ];

    private const string FILL = 'ABCDEFGHIJKLMNOPRSTUVWY';

    /**
     * @param  list<string>  $words
     * @param  list<string>  $directions  keys of DIRECTIONS
     * @return array{grid: list<list<string>>, found: list<array{word: string, start: array{0: int, 1: int}, end: array{0: int, 1: int}}>}
     */
    public function build(array $words, int $size, array $directions, string $seed): array
    {
        $words = array_values(array_unique(array_map(fn (string $w) => Words::display($w), $words)));
        usort($words, fn (string $a, string $b) => mb_strlen($b) <=> mb_strlen($a) ?: strcmp($a, $b));

        for ($attempt = 0; $attempt < 30; $attempt++) {
            $rng = new Randomizer(new Xoshiro256StarStar(hash('sha256', "wordsearch|{$size}|{$attempt}|{$seed}", true)));
            $result = $this->tryBuild($words, $size, $directions, $rng);

            if ($result !== null) {
                return $result;
            }
        }

        throw new RuntimeException('Could not place all words in a '.$size.'×'.$size.' grid: '.implode(', ', $words));
    }

    /**
     * Whether the letters from $start to $end (a straight line in one of the 8 directions) spell
     * $word, in either reading direction (start/end in either order), compared Unicode-aware.
     *
     * @param  list<list<string>>  $grid
     * @param  array{0: int, 1: int}  $start
     * @param  array{0: int, 1: int}  $end
     */
    public static function spells(array $grid, string $word, array $start, array $end): bool
    {
        $length = count(Words::letters(trim($word)));
        $dr = $end[0] <=> $start[0];
        $dc = $end[1] <=> $start[1];
        $rowSpan = abs($end[0] - $start[0]);
        $colSpan = abs($end[1] - $start[1]);

        if ($length < 2 || ($rowSpan !== 0 && $colSpan !== 0 && $rowSpan !== $colSpan) || max($rowSpan, $colSpan) !== $length - 1) {
            return false;
        }

        $letters = [];

        for ($i = 0; $i < $length; $i++) {
            $letters[] = (string) ($grid[$start[0] + $i * $dr][$start[1] + $i * $dc] ?? '');
        }

        return Words::equals(implode('', $letters), $word) || Words::equals(implode('', array_reverse($letters)), $word);
    }

    /**
     * @param  list<string>  $words
     * @param  list<string>  $directions
     * @return array{grid: list<list<string>>, found: list<array{word: string, start: array{0: int, 1: int}, end: array{0: int, 1: int}}>}|null
     */
    private function tryBuild(array $words, int $size, array $directions, Randomizer $rng): ?array
    {
        $grid = array_fill(0, $size, array_fill(0, $size, ''));
        $found = [];

        foreach ($words as $word) {
            $placement = $this->place($grid, $word, $size, $directions, $rng);

            if ($placement === null) {
                return null;
            }

            $found[] = ['word' => $word, 'start' => $placement[0], 'end' => $placement[1]];
        }

        $fill = str_split(self::FILL);

        foreach ($grid as $r => $row) {
            foreach ($row as $c => $letter) {
                if ($letter === '') {
                    $grid[$r][$c] = $fill[$rng->getInt(0, count($fill) - 1)];
                }
            }
        }

        return ['grid' => $grid, 'found' => $found];
    }

    /**
     * @param  array<int, array<int, string>>  $grid
     * @param  list<string>  $directions
     * @return array{0: array{0: int, 1: int}, 1: array{0: int, 1: int}}|null
     */
    private function place(array &$grid, string $word, int $size, array $directions, Randomizer $rng): ?array
    {
        $chars = Words::letters($word);
        $length = count($chars);

        for ($try = 0; $try < 200; $try++) {
            [$dr, $dc] = self::DIRECTIONS[$directions[$rng->getInt(0, count($directions) - 1)]];
            $row = $rng->getInt(0, $size - 1);
            $col = $rng->getInt(0, $size - 1);
            $endRow = $row + $dr * ($length - 1);
            $endCol = $col + $dc * ($length - 1);

            if ($endRow < 0 || $endRow >= $size || $endCol < 0 || $endCol >= $size) {
                continue;
            }

            $fits = true;

            for ($i = 0; $i < $length; $i++) {
                $existing = $grid[$row + $i * $dr][$col + $i * $dc];

                if ($existing !== '' && $existing !== $chars[$i]) {
                    $fits = false;
                    break;
                }
            }

            if (! $fits) {
                continue;
            }

            for ($i = 0; $i < $length; $i++) {
                $grid[$row + $i * $dr][$col + $i * $dc] = $chars[$i];
            }

            return [[$row, $col], [$endRow, $endCol]];
        }

        return null;
    }
}
