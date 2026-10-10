<?php

namespace App\Modules\Scoring;

use App\Modules\Core\ModuleServiceProvider;
use App\Modules\Play\Events\AnswerSubmitted;
use App\Modules\Play\Events\SessionCompleted;
use App\Modules\Scoring\Listeners\RecordAnswerPoints;
use App\Modules\Scoring\Listeners\RefreshMemberTotals;
use App\Modules\Scoring\Listeners\UpdateStreak;

class ScoringServiceProvider extends ModuleServiceProvider
{
    protected array $listen = [
        AnswerSubmitted::class => [RecordAnswerPoints::class, UpdateStreak::class, RefreshMemberTotals::class],
        SessionCompleted::class => [RefreshMemberTotals::class],
    ];
}
