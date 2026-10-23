<?php

namespace App\Modules\Policy;

use App\Modules\Core\ModuleServiceProvider;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Policy\Contracts\SecondChanceUsage;
use App\Modules\Policy\Support\ThemeWorldCatalog;

class PolicyServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        // Fallback until a module that tracks second chances (Play) binds a real counter.
        if (! $this->app->bound(SecondChanceUsage::class)) {
            $this->app->bind(SecondChanceUsage::class, fn () => new class implements SecondChanceUsage
            {
                public function freeUsedToday(FamilyMember $member): int
                {
                    return 0;
                }
            });
        }

        // Scoped: fresh per request / queued job, so long-running workers never keep stale rules.
        $this->app->scoped(PolicyResolver::class);
        $this->app->scoped(ThemeWorldCatalog::class);
    }
}
