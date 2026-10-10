<?php

use App\Modules\ChallengeTypes\ChallengeTypesServiceProvider;
use App\Modules\Content\ContentServiceProvider;
use App\Modules\Core\CoreServiceProvider;
use App\Modules\Family\FamilyServiceProvider;
use App\Modules\Identity\IdentityServiceProvider;
use App\Modules\Play\PlayServiceProvider;
use App\Modules\Policy\PolicyServiceProvider;
use App\Modules\Scoring\ScoringServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\HorizonServiceProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    HorizonServiceProvider::class,

    // Domain modules (app/Modules/<Name>)
    CoreServiceProvider::class,
    PolicyServiceProvider::class,
    IdentityServiceProvider::class,
    FamilyServiceProvider::class,
    ChallengeTypesServiceProvider::class,
    ContentServiceProvider::class,
    PlayServiceProvider::class,
    ScoringServiceProvider::class,
];
