<?php

namespace App\Modules\ChallengeTypes\Generators;

use App\Modules\ChallengeTypes\Support\ClanArt;

/**
 * Perfect mazes (docs/API.md "Level-up puzzles"): a seeded recursive backtracker (iterative
 * depth-first search) carves a spanning tree of the grid, so there is exactly one route between
 * any two cells, every neighbour pair agrees on its wall and the outer border stays walled.
 *
 * Cells are wall bitmasks: 1 north, 2 east, 4 south, 8 west. `start` is the top-left cell,
 * `goal` the cell farthest from it (ties: the last one in row-major order). Stars go on
 * distinct cells other than start and goal, dead ends off the main route first (little
 * detours to fetch them), then other cells off the route, then anywhere. The answer is a
 * shortest walk from start through every star to goal: every visiting order of the stars (at
 * most 4! = 24) is scored with BFS distances and the cheapest is spelled out cell by cell. In a
 * tree such a walk crosses each corridor at most twice, so it stays under 2 × rows × cols cells.
 */
class MazeGenerator
{
    /** Wall bits: direction => [bit, row step, col step, opposite bit]. */
    public const array DIRECTIONS = [
        'n' => [1, -1, 0, 4],
        'e' => [2, 0, 1, 8],
        's' => [4, 1, 0, 1],
        'w' => [8, 0, -1, 2],
    ];

    /**
     * @return array{rows: int, cols: int, cells: list<list<int>>, start: array{0: int, 1: int}, goal: array{0: int, 1: int}, stars: list<array{0: int, 1: int}>, path: list<array{0: int, 1: int}>}
     */
    public function generate(string $seed, int $difficulty, int $size, int $stars): array
    {
        $rng = ClanArt::random('maze', $seed, $difficulty);
        $cells = array_fill(0, $size, array_fill(0, $size, 15));
        $visited = [0 => true];
        $stack = [[0, 0]];

        while ($stack !== []) {
            [$r, $c] = $stack[count($stack) - 1];
            $options = [];

            foreach (self::DIRECTIONS as [$bit, $dr, $dc, $opposite]) {
                $nr = $r + $dr;
                $nc = $c + $dc;

                if ($nr >= 0 && $nr < $size && $nc >= 0 && $nc < $size && ! isset($visited[$nr * $size + $nc])) {
                    $options[] = [$nr, $nc, $bit, $opposite];
                }
            }

            if ($options === []) {
                array_pop($stack);

                continue;
            }

            [$nr, $nc, $bit, $opposite] = $options[$rng->getInt(0, count($options) - 1)];
            $cells[$r][$c] &= ~$bit;
            $cells[$nr][$nc] &= ~$opposite;
            $visited[$nr * $size + $nc] = true;
            $stack[] = [$nr, $nc];
        }

        $start = [0, 0];
        $fromStart = self::distances($cells, $start);
        $goal = $start;

        $farthest = 0;

        for ($index = 0; $index < $size * $size; $index++) {
            if (($fromStart['dist'][$index] ?? 0) >= $farthest) {
                $farthest = $fromStart['dist'][$index] ?? 0;
                $goal = [intdiv($index, $size), $index % $size];
            }
        }

        $route = self::pathTo($fromStart['parent'], $size, $goal);
        $onRoute = array_flip(array_map(fn (array $cell) => $cell[0] * $size + $cell[1], $route));
        $tiers = [[], [], []];

        for ($index = 0; $index < $size * $size; $index++) {
            if (isset($onRoute[$index])) {
                $tiers[2][] = $index;

                continue;
            }

            $tiers[self::openSides($cells[intdiv($index, $size)][$index % $size]) === 1 ? 0 : 1][] = $index;
        }

        $tiers[2] = array_values(array_diff($tiers[2], [0, $goal[0] * $size + $goal[1]]));
        $picked = [];

        foreach ($tiers as $tier) {
            foreach ($rng->shuffleArray($tier) as $index) {
                if (count($picked) < $stars) {
                    $picked[] = $index;
                }
            }
        }

        sort($picked);
        $starCells = array_map(fn (int $index) => [intdiv($index, $size), $index % $size], $picked);

        return [
            'rows' => $size,
            'cols' => $size,
            'cells' => $cells,
            'start' => $start,
            'goal' => $goal,
            'stars' => $starCells,
            'path' => self::shortestTour($cells, $start, $goal, $starCells),
        ];
    }

    /**
     * A shortest walk start → every star (any order) → goal, cell by cell.
     *
     * @param  list<list<int>>  $cells
     * @param  array{0: int, 1: int}  $start
     * @param  array{0: int, 1: int}  $goal
     * @param  list<array{0: int, 1: int}>  $stars
     * @return list<array{0: int, 1: int}>
     */
    public static function shortestTour(array $cells, array $start, array $goal, array $stars): array
    {
        $size = count($cells[0] ?? []);
        $points = [$start, ...$stars];
        $bfs = array_map(fn (array $point) => self::distances($cells, $point), $points);
        $best = null;
        $bestCost = PHP_INT_MAX;

        foreach ($stars === [] ? [[]] : self::permutations(range(1, count($stars))) as $order) {
            $cost = 0;
            $from = 0;

            foreach ([...$order, -1] as $to) {
                $target = $to === -1 ? $goal : $points[$to];
                $cost += $bfs[$from]['dist'][$target[0] * $size + $target[1]] ?? PHP_INT_MAX;
                $from = $to;
            }

            if ($cost < $bestCost) {
                $bestCost = $cost;
                $best = $order;
            }
        }

        $path = [$start];
        $from = 0;

        foreach ([...($best ?? []), -1] as $to) {
            $target = $to === -1 ? $goal : $points[$to];
            $leg = self::pathTo($bfs[$from]['parent'], $size, $target);
            array_push($path, ...array_slice($leg, 1));
            $from = $to;
        }

        return $path;
    }

    /**
     * Breadth-first distances (and parents) from a cell through open walls, keyed r × size + c.
     *
     * @param  list<list<int>>  $cells
     * @param  array{0: int, 1: int}  $from
     * @return array{dist: array<int, int>, parent: array<int, int>}
     */
    public static function distances(array $cells, array $from): array
    {
        $rows = count($cells);
        $size = count($cells[0] ?? []);
        $origin = $from[0] * $size + $from[1];
        $dist = [$origin => 0];
        $parent = [$origin => -1];
        $queue = [$origin];

        for ($head = 0; $head < count($queue); $head++) {
            $index = $queue[$head];
            $r = intdiv($index, $size);
            $c = $index % $size;

            foreach (self::DIRECTIONS as [$bit, $dr, $dc]) {
                $nr = $r + $dr;
                $nc = $c + $dc;
                $next = $nr * $size + $nc;

                if (($cells[$r][$c] & $bit) === 0 && $nr >= 0 && $nr < $rows && $nc >= 0 && $nc < $size && ! isset($dist[$next])) {
                    $dist[$next] = $dist[$index] + 1;
                    $parent[$next] = $index;
                    $queue[] = $next;
                }
            }
        }

        return ['dist' => $dist, 'parent' => $parent];
    }

    public static function openSides(int $walls): int
    {
        return 4 - (($walls & 1) + (($walls >> 1) & 1) + (($walls >> 2) & 1) + (($walls >> 3) & 1));
    }

    /**
     * @param  array<int, int>  $parent
     * @param  array{0: int, 1: int}  $to
     * @return list<array{0: int, 1: int}>
     */
    private static function pathTo(array $parent, int $size, array $to): array
    {
        $path = [];
        $index = $to[0] * $size + $to[1];
        $guard = count($parent);

        while ($index !== -1 && isset($parent[$index]) && $guard-- >= 0) {
            $path[] = [intdiv($index, $size), $index % $size];
            $index = $parent[$index];
        }

        return array_reverse($path);
    }

    /**
     * @param  list<int>  $items
     * @return list<list<int>>
     */
    private static function permutations(array $items): array
    {
        if (count($items) <= 1) {
            return [$items];
        }

        $all = [];

        foreach ($items as $i => $item) {
            $rest = $items;
            unset($rest[$i]);

            foreach (self::permutations(array_values($rest)) as $tail) {
                $all[] = [$item, ...$tail];
            }
        }

        return $all;
    }
}
