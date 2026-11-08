<?php

use App\Modules\ChallengeTypes\Handlers\CodeBreaker;
use App\Modules\ChallengeTypes\Handlers\CrossMath;
use App\Modules\ChallengeTypes\Handlers\MathSprint;
use App\Modules\ChallengeTypes\Handlers\Maze;
use App\Modules\ChallengeTypes\Handlers\MemoryMatch;
use App\Modules\ChallengeTypes\Handlers\MultipleChoice;
use App\Modules\ChallengeTypes\Handlers\Nonogram;
use App\Modules\ChallengeTypes\Handlers\PatternSequence;
use App\Modules\ChallengeTypes\Handlers\SpotDifference;
use App\Modules\ChallengeTypes\Handlers\Sudoku;
use App\Modules\ChallengeTypes\Handlers\WisdomCard;
use App\Modules\ChallengeTypes\Handlers\WordPuzzle;

/*
 * Challenge type plugins available in this build: key => handler class.
 * Each class implements App\Modules\ChallengeTypes\Contracts\ChallengeType; types that also
 * implement GeneratesItems (sudoku, math_sprint, the brain games memory_match,
 * pattern_sequence, nonogram, spot_difference and the level-up puzzles cross_math, maze,
 * code_breaker) are generated per session from the generators a category declares
 * (`categories.generators`) instead of being picked from content items.
 * Admins can switch a type off in the `challenge_types` table (Filament → Content → Challenge types).
 */

return [
    'handlers' => [
        'multiple_choice' => MultipleChoice::class,
        'wisdom_card' => WisdomCard::class,
        'sudoku' => Sudoku::class,
        'math_sprint' => MathSprint::class,
        'word_puzzle' => WordPuzzle::class,
        'memory_match' => MemoryMatch::class,
        'pattern_sequence' => PatternSequence::class,
        'nonogram' => Nonogram::class,
        'spot_difference' => SpotDifference::class,
        'cross_math' => CrossMath::class,
        'maze' => Maze::class,
        'code_breaker' => CodeBreaker::class,
    ],
];
