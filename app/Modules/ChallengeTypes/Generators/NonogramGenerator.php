<?php

namespace App\Modules\ChallengeTypes\Generators;

use App\Modules\ChallengeTypes\Support\ClanArt;
use Random\Randomizer;

/**
 * Nonogram pictures, clues and a line solver.
 *
 * Pictures come from a hand-drawn library (database/seeders/data/nonograms.php: per size, a
 * key with en/fr titles and rows drawn as `#` = filled, `.` = empty); a seeded pick may be
 * mirrored left–right for variety. A puzzle is accepted only when the line solver (row/column
 * logic alone, no guessing) solves it completely, which proves its solution is unique. When a
 * size has no usable picture the generator falls back to a random picture that passes the
 * same check.
 *
 * Grids are row arrays of 0/1; clues are the run lengths of each row (left → right) and column
 * (top → bottom). Library pictures never have an empty row or column, so no clue is empty.
 */
class NonogramGenerator
{
    /**
     * Library pictures, loaded once: size => key => {en, fr, rows}.
     *
     * @var array<int, array<string, array{en: string, fr: string, rows: list<string>}>>|null
     */
    private static ?array $library = null;

    /** @var array<int, array<string, array{en: string, fr: string, rows: list<string>}>> */
    private static array $usable = [];

    /** Titles of random fallback pictures. */
    public const array RANDOM_TITLES = ['en' => 'A mystery pattern', 'fr' => 'Un motif mystère'];

    /**
     * @return array{solution: list<list<int>>, picture: string, titles: array{en: string, fr: string}}
     */
    public function generate(string $seed, int $size): array
    {
        $rng = ClanArt::random('nonogram', $seed, $size);
        $pictures = $this->usablePictures($size);

        if ($pictures === []) {
            return ['solution' => $this->randomPicture($rng, $size), 'picture' => 'random', 'titles' => self::RANDOM_TITLES];
        }

        $keys = array_keys($pictures);
        $key = $keys[$rng->getInt(0, count($keys) - 1)];
        $grid = self::toGrid($pictures[$key]['rows']);

        if ($rng->getInt(0, 1) === 1) {
            $grid = array_map(fn (array $row) => array_reverse($row), $grid);
        }

        return ['solution' => $grid, 'picture' => $key, 'titles' => ['en' => $pictures[$key]['en'], 'fr' => $pictures[$key]['fr']]];
    }

    /**
     * Library pictures of a size that pass isUsable() (checked once per process).
     *
     * @return array<string, array{en: string, fr: string, rows: list<string>}>
     */
    public function usablePictures(int $size): array
    {
        return self::$usable[$size] ??= array_filter(self::library()[$size] ?? [], fn (array $picture) => $this->isUsable(self::toGrid($picture['rows']), $size));
    }

    /**
     * The picture library (database/seeders/data/nonograms.php).
     *
     * @return array<int, array<string, array{en: string, fr: string, rows: list<string>}>>
     */
    public static function library(): array
    {
        /** @var array<int, array<string, array{en: string, fr: string, rows: list<string>}>> $library */
        $library = self::$library ??= require database_path('seeders/data/nonograms.php');

        return $library;
    }

    /**
     * `#`/`.` rows → 0/1 grid.
     *
     * @param  list<string>  $rows
     * @return list<list<int>>
     */
    public static function toGrid(array $rows): array
    {
        return array_map(fn (string $row) => array_map(fn (string $cell) => $cell === '#' ? 1 : 0, str_split($row)), array_values($rows));
    }

    /**
     * Run lengths of one line.
     *
     * @param  list<int>  $line
     * @return list<int>
     */
    public static function lineClues(array $line): array
    {
        $clues = [];
        $run = 0;

        foreach ($line as $cell) {
            if ($cell === 1) {
                $run++;
            } elseif ($run > 0) {
                $clues[] = $run;
                $run = 0;
            }
        }

        if ($run > 0) {
            $clues[] = $run;
        }

        return $clues;
    }

    /**
     * Row and column clues of a grid.
     *
     * @param  list<list<int>>  $grid
     * @return array{rows: list<list<int>>, cols: list<list<int>>}
     */
    public static function clues(array $grid): array
    {
        $cols = [];

        for ($c = 0; $c < count($grid[0] ?? []); $c++) {
            $cols[] = self::lineClues(array_column($grid, $c));
        }

        return ['rows' => array_map(fn (array $row) => self::lineClues($row), $grid), 'cols' => $cols];
    }

    /**
     * Whether a picture makes a good puzzle: the right size, no empty row or column, and solvable
     * line by line (so the solution is unique).
     *
     * @param  list<list<int>>  $grid
     */
    public function isUsable(array $grid, int $size): bool
    {
        if (count($grid) !== $size) {
            return false;
        }

        foreach ($grid as $row) {
            if (count($row) !== $size) {
                return false;
            }
        }

        $clues = self::clues($grid);

        foreach ([...$clues['rows'], ...$clues['cols']] as $line) {
            if ($line === []) {
                return false;
            }
        }

        return $this->solve($clues['rows'], $clues['cols']) === $grid;
    }

    /**
     * Solves a puzzle with line logic only: the grid when that settles every cell (then it is
     * the only solution), null when the clues contradict or line logic gets stuck.
     *
     * @param  list<list<int>>  $rowClues
     * @param  list<list<int>>  $colClues
     * @return list<list<int>>|null
     */
    public function solve(array $rowClues, array $colClues): ?array
    {
        $rows = count($rowClues);
        $cols = count($colClues);
        $grid = array_fill(0, $rows, array_fill(0, $cols, -1));
        $memo = [];
        $settle = function (array $clues, array $cells) use (&$memo): ?array {
            $key = implode(',', $clues).'|'.implode('', array_map(fn (int $cell) => $cell + 1, $cells));

            return array_key_exists($key, $memo) ? $memo[$key] : ($memo[$key] = $this->solveLine($clues, $cells));
        };

        do {
            $changed = false;

            for ($r = 0; $r < $rows; $r++) {
                $line = $settle($rowClues[$r], $grid[$r]);

                if ($line === null) {
                    return null;
                }

                if ($line !== $grid[$r]) {
                    $grid[$r] = $line;
                    $changed = true;
                }
            }

            for ($c = 0; $c < $cols; $c++) {
                $column = array_column($grid, $c);
                $line = $settle($colClues[$c], $column);

                if ($line === null) {
                    return null;
                }

                if ($line !== $column) {
                    foreach ($line as $r => $value) {
                        $grid[$r][$c] = $value;
                    }

                    $changed = true;
                }
            }
        } while ($changed);

        foreach ($grid as $row) {
            if (in_array(-1, $row, true)) {
                return null;
            }
        }

        return array_values(array_map(fn (array $row) => array_values(array_map(fn (int $cell) => $cell === 1 ? 1 : 0, $row)), $grid));
    }

    /**
     * Settles the cells of one line (-1 unknown, 0 empty, 1 filled) that every placement of the
     * clue runs consistent with the known cells agrees on. Null when no placement fits.
     *
     * @param  list<int>  $clues
     * @param  list<int>  $cells
     * @return list<int>|null
     */
    public function solveLine(array $clues, array $cells): ?array
    {
        $length = count($cells);
        $canFill = array_fill(0, $length, false);
        $canEmpty = array_fill(0, $length, false);
        $found = false;

        $this->place($clues, 0, 0, array_fill(0, $length, 0), $cells, $canFill, $canEmpty, $found);

        if (! $found) {
            return null;
        }

        $result = $cells;

        for ($i = 0; $i < $length; $i++) {
            if ($canFill[$i] && ! $canEmpty[$i]) {
                $result[$i] = 1;
            } elseif ($canEmpty[$i] && ! $canFill[$i]) {
                $result[$i] = 0;
            }
        }

        return $result;
    }

    /**
     * A random picture of the size that passes isUsable() (a full grid if none turns up).
     *
     * @return list<list<int>>
     */
    public function randomPicture(Randomizer $rng, int $size): array
    {
        for ($attempt = 0; $attempt < 500; $attempt++) {
            $grid = [];

            for ($r = 0; $r < $size; $r++) {
                $row = [];

                for ($c = 0; $c < $size; $c++) {
                    $row[] = $rng->getInt(1, 100) <= 62 ? 1 : 0;
                }

                $grid[] = $row;
            }

            if ($this->isUsable($grid, $size)) {
                return $grid;
            }
        }

        return array_fill(0, $size, array_fill(0, $size, 1));
    }

    /**
     * Enumerates placements of runs $clues[$index…] from $start on, recording which cells can be
     * filled or empty.
     *
     * @param  list<int>  $clues
     * @param  list<int>  $line  the placement being built
     * @param  list<int>  $cells  the known cells
     * @param  array<int, bool>  $canFill
     * @param  array<int, bool>  $canEmpty
     */
    private function place(array $clues, int $index, int $start, array $line, array $cells, array &$canFill, array &$canEmpty, bool &$found): void
    {
        $length = count($cells);

        if ($index === count($clues)) {
            for ($i = $start; $i < $length; $i++) {
                if ($cells[$i] === 1) {
                    return;
                }
            }

            $found = true;

            foreach ($line as $i => $value) {
                if ($value === 1) {
                    $canFill[$i] = true;
                } else {
                    $canEmpty[$i] = true;
                }
            }

            return;
        }

        $run = $clues[$index];
        $rest = array_sum(array_slice($clues, $index + 1)) + count($clues) - $index - 1;

        for ($at = $start; $at + $run + $rest <= $length; $at++) {
            if ($at > $start && $cells[$at - 1] === 1) {
                return;
            }

            $fits = true;

            for ($i = $at; $i < $at + $run; $i++) {
                if ($cells[$i] === 0) {
                    $fits = false;
                    break;
                }
            }

            if (! $fits || ($at + $run < $length && $cells[$at + $run] === 1)) {
                continue;
            }

            $next = $line;

            for ($i = $at; $i < $at + $run; $i++) {
                $next[$i] = 1;
            }

            $this->place($clues, $index + 1, min($length, $at + $run + 1), $next, $cells, $canFill, $canEmpty, $found);
        }
    }
}
