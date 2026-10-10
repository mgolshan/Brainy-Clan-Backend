<?php

namespace App\Modules\ChallengeTypes;

use App\Modules\Core\ModuleServiceProvider;

class ChallengeTypesServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(ChallengeTypeRegistry::class);
    }
}
