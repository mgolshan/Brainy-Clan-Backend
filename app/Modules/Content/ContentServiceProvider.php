<?php

namespace App\Modules\Content;

use App\Modules\Content\Support\CategoryTree;
use App\Modules\Core\ModuleServiceProvider;

class ContentServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(CategoryTree::class);
    }
}
