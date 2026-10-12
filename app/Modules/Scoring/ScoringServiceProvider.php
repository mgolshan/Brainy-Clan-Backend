<?php

namespace App\Modules\Scoring;

use App\Modules\Core\ModuleServiceProvider;
use App\Modules\Play\Events\AnswerSubmitted;
use App\Modules\Scoring\Listeners\RecordAnswerPoints;
use App\Modules\Scoring\Listeners\UpdateStreak;

/**
 * Goal progress is computed from the ledger on read (Goals module), so there is no per-period
 * totals cache to refresh here.
 */
class ScoringServiceProvider extends ModuleServiceProvider
{
    protected array $listen = [
        AnswerSubmitted::class => [RecordAnswerPoints::class, UpdateStreak::class],
    ];
}
