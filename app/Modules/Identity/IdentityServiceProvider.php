<?php

namespace App\Modules\Identity;

use App\Modules\Core\ModuleServiceProvider;
use App\Modules\Identity\Apple\AppleTokenClient;
use App\Modules\Identity\Apple\HttpAppleTokenClient;

class IdentityServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        parent::register();

        $this->app->bind(AppleTokenClient::class, HttpAppleTokenClient::class);
    }
}
