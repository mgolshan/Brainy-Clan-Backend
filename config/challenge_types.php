<?php

use App\Modules\ChallengeTypes\Handlers\MultipleChoice;
use App\Modules\ChallengeTypes\Handlers\WisdomCard;

/*
 * Challenge type plugins available in this build: key => handler class.
 * Each class implements App\Modules\ChallengeTypes\Contracts\ChallengeType.
 * Admins can switch a type off in the `challenge_types` table (Filament → Content → Challenge types).
 */

return [
    'handlers' => [
        'multiple_choice' => MultipleChoice::class,
        'wisdom_card' => WisdomCard::class,
        // B7: 'sudoku', 'word_puzzle', 'math_sprint'
    ],
];
