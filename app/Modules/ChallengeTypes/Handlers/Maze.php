<?php

namespace App\Modules\ChallengeTypes\Handlers;

use App\Modules\ChallengeTypes\Generators\MazeGenerator;
use App\Modules\Content\Models\ContentItem;

/**
 * Maze: walk from start to goal, collecting every star (docs/API.md "Level-up puzzles").
 *
 * payload:  { "rows": 8, "cols": 8, "cells": [[9,5,3,…],…], "start": [0,0], "goal": [7,7], "stars": [[2,5],…] }
 * answer:   { "path": [[0,0],[0,1],…] }                 (a shortest route through all stars)
 * response: { "path": [[0,0],[0,1],[1,1],…] }           (every cell walked through, in order)
 *
 * `cells[r][c]` is a wall bitmask (1 north, 2 east, 4 south, 8 west); the maze is perfect and
 * walled all around. The path must start on `start` and each step go to an orthogonal neighbour
 * with no wall in between (revisits allowed); otherwise, or with a cell out of range or more than
 * rows × cols × 4 entries, it is a 422. Correct when it ends on `goal` having visited every star.
 * Sizes 5–16 by difficulty; 0–4 stars; worth 1 (≤ 7×7), 2 (8–10), 3 (11–12) or 5 points (14+).
 */
class Maze extends BrainGame
{
    /** Maze side per difficulty 1–10. */
    public const array SIZES = [1 => 5, 2 => 6, 3 => 7, 4 => 8, 5 => 9, 6 => 10, 7 => 11, 8 => 12, 9 => 14, 10 => 16];

    /** Stars per difficulty 1–10. */
    public const array STARS = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 1, 6 => 2, 7 => 3, 8 => 3, 9 => 4, 10 => 4];

    /** Most stars a maze may have. */
    public const int MAX_STARS = 4;

    public function __construct(private readonly MazeGenerator $generator) {}

    public function key(): string
    {
        return 'maze';
    }

    public function name(): string
    {
        return 'Maze';
    }

    public static function pointsForSize(int $size): int
    {
        return match (true) {
            $size <= 7 => 1,
            $size <= 10 => 2,
            $size <= 12 => 3,
            default => 5,
        };
    }

    public function validate(array $payload, array $answer): array
    {
        $rows = $payload['rows'] ?? null;
        $cols = $payload['cols'] ?? null;

        if (! is_int($rows) || ! is_int($cols) || $rows < 2 || $rows > 20 || $cols < 2 || $cols > 20) {
            return ['payload.rows' => ['rows and cols must be 2–20.']];
        }

        $cells = $payload['cells'] ?? null;

        if (! $this->isPerfectMaze($cells, $rows, $cols)) {
            return ['payload.cells' => ["cells must be {$rows} rows of {$cols} wall bitmasks (0–15) forming a perfect maze: border walled, neighbours agree, exactly one route between any two cells."]];
        }

        $errors = [];
        $start = $payload['start'] ?? null;
        $goal = $payload['goal'] ?? null;

        if (! $this->isCell($start, $rows, $cols)) {
            $errors['payload.start'][] = 'start must be a [row, col] cell of the maze.';
        }

        if (! $this->isCell($goal, $rows, $cols) || $goal === $start) {
            $errors['payload.goal'][] = 'goal must be a [row, col] cell of the maze, not the start.';
        }

        $stars = $payload['stars'] ?? null;

        if (! is_array($stars) || ! array_is_list($stars) || count($stars) > self::MAX_STARS
            || array_filter($stars, fn (mixed $star) => ! $this->isCell($star, $rows, $cols) || $star === $start || $star === $goal) !== []
            || count(array_unique(array_map('json_encode', $stars))) !== count($stars)) {
            $errors['payload.stars'][] = 'stars must be 0–'.self::MAX_STARS.' different cells, not the start or the goal.';
        }

        if ($errors === []) {
            $item = (new ContentItem)->forceFill(['payload' => $payload]);
            $path = $answer['path'] ?? null;

            if ($this->replay($item, $path) !== null || ! $this->check($item, ['path' => $path])) {
                $errors['answer.path'][] = 'path must walk from start through every star to the goal.';
            }
        }

        return $errors;
    }

    public function forClient(ContentItem $item): array
    {
        return [
            'rows' => (int) ($item->payload['rows'] ?? 0),
            'cols' => (int) ($item->payload['cols'] ?? 0),
            'cells' => array_values((array) ($item->payload['cells'] ?? [])),
            'start' => array_values((array) ($item->payload['start'] ?? [])),
            'goal' => array_values((array) ($item->payload['goal'] ?? [])),
            'stars' => array_values((array) ($item->payload['stars'] ?? [])),
        ];
    }

    public function validateResponse(ContentItem $item, array $response): array
    {
        $error = $this->replay($item, $response['path'] ?? null);

        return $error !== null ? ['response.path' => [$error]] : [];
    }

    public function check(ContentItem $item, array $response): bool
    {
        $path = $response['path'] ?? null;

        if ($this->replay($item, $path) !== null) {
            return false;
        }

        /** @var list<array{0: int, 1: int}> $path */
        $visited = array_flip(array_map(fn (array $cell) => "{$cell[0]}:{$cell[1]}", $path));

        foreach ((array) ($item->payload['stars'] ?? []) as $star) {
            if (! is_array($star) || ! isset($visited[($star[0] ?? '').':'.($star[1] ?? '')])) {
                return false;
            }
        }

        return $path[count($path) - 1] === array_values((array) ($item->payload['goal'] ?? []));
    }

    public function correctResponse(ContentItem $item): array
    {
        return ['path' => array_values((array) ($item->answer['path'] ?? []))];
    }

    public function points(ContentItem $item): int
    {
        return self::pointsForSize(max((int) ($item->payload['rows'] ?? 0), (int) ($item->payload['cols'] ?? 0)));
    }

    public function generate(string $seed, int $difficulty, array $options): array
    {
        $difficulty = self::clampDifficulty($difficulty);
        $maze = $this->generator->generate($seed, $difficulty, self::SIZES[$difficulty], self::STARS[$difficulty]);
        $path = $maze['path'];
        unset($maze['path']);

        return ['payload' => $maze, 'answer' => ['path' => $path]];
    }

    public function pointsFor(array $options, int $difficulty): int
    {
        return self::pointsForSize(self::SIZES[self::clampDifficulty($difficulty)]);
    }

    /**
     * Why a path is malformed (null = well-formed): not a list of cells, too long, not starting
     * on `start`, a cell out of range, or a step that is not to an open neighbour.
     */
    private function replay(ContentItem $item, mixed $path): ?string
    {
        $rows = (int) ($item->payload['rows'] ?? 0);
        $cols = (int) ($item->payload['cols'] ?? 0);
        $cells = (array) ($item->payload['cells'] ?? []);
        $limit = $rows * $cols * 4;

        if (! is_array($path) || ! array_is_list($path) || $path === [] || count($path) > $limit) {
            return "Send the path as a list of [row, col] cells, starting on the start (at most {$limit}).";
        }

        if ($path[0] !== array_values((array) ($item->payload['start'] ?? []))) {
            return 'The path must start on the start cell.';
        }

        foreach ($path as $index => $cell) {
            if (! $this->isCell($cell, $rows, $cols)) {
                return "Step {$index} is not a cell of the maze.";
            }

            if ($index === 0) {
                continue;
            }

            /** @var array{0: int, 1: int} $from */
            $from = $path[$index - 1];
            $direction = match ([$cell[0] - $from[0], $cell[1] - $from[1]]) {
                [-1, 0] => 1,
                [0, 1] => 2,
                [1, 0] => 4,
                [0, -1] => 8,
                default => null,
            };

            if ($direction === null || ((int) ($cells[$from[0]][$from[1]] ?? 15) & $direction) !== 0) {
                return "Step {$index} goes through a wall or to a cell that is not next to the previous one.";
            }
        }

        return null;
    }

    /**
     * @phpstan-assert-if-true array{0: int, 1: int} $cell
     */
    private function isCell(mixed $cell, int $rows, int $cols): bool
    {
        return is_array($cell) && array_is_list($cell) && count($cell) === 2
            && is_int($cell[0]) && is_int($cell[1])
            && $cell[0] >= 0 && $cell[0] < $rows && $cell[1] >= 0 && $cell[1] < $cols;
    }

    /**
     * Wall bitmasks with the border walled, neighbours agreeing, all cells connected and exactly
     * rows × cols − 1 openings (a spanning tree: one route between any two cells).
     */
    private function isPerfectMaze(mixed $cells, int $rows, int $cols): bool
    {
        if (! is_array($cells) || ! array_is_list($cells) || count($cells) !== $rows) {
            return false;
        }

        foreach ($cells as $row) {
            if (! self::isIntList($row, 0, 15) || count($row) !== $cols) {
                return false;
            }
        }

        /** @var list<list<int>> $cells */
        $openings = 0;

        foreach ($cells as $r => $row) {
            foreach ($row as $c => $walls) {
                if (($r === 0 && ($walls & 1) === 0) || ($r === $rows - 1 && ($walls & 4) === 0)
                    || ($c === 0 && ($walls & 8) === 0) || ($c === $cols - 1 && ($walls & 2) === 0)) {
                    return false;
                }

                if ($c + 1 < $cols && (($walls & 2) === 0) !== (($cells[$r][$c + 1] & 8) === 0)) {
                    return false;
                }

                if ($r + 1 < $rows && (($walls & 4) === 0) !== (($cells[$r + 1][$c] & 1) === 0)) {
                    return false;
                }

                $openings += (($walls & 2) === 0 ? 1 : 0) + (($walls & 4) === 0 ? 1 : 0);
            }
        }

        return $openings === $rows * $cols - 1 && count(MazeGenerator::distances($cells, [0, 0])['dist']) === $rows * $cols;
    }
}
