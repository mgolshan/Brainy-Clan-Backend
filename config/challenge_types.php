<?php

use App\Modules\ChallengeTypes\Handlers\MathSprint;
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
 * implement GeneratesItems (sudoku, math_sprint and the brain games memory_match,
 * pattern_sequence, nonogram, spot_difference) are generated per session from the generators
 * a category declares (`categories.generators`) instead of being picked from content items.
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
    ],
];
