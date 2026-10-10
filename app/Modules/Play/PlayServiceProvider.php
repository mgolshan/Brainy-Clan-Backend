<?php

namespace App\Modules\Play;

use App\Modules\Core\ModuleServiceProvider;
use App\Modules\Play\Support\FreeSecondChanceCounter;
use App\Modules\Policy\Contracts\SecondChanceUsage;

class PlayServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SecondChanceUsage::class, FreeSecondChanceCounter::class);
    }
}
