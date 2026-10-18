<?php

namespace App\Modules\Core\Console;

use App\Modules\Core\Support\ReferenceCache;
use Illuminate\Console\Command;

/**
 * After editing age bands, settings, categories, challenge types or content items with raw SQL
 * (bypassing Eloquent): invalidate only the reference caches, not throttles or other entries.
 */
class FlushReferenceCachesCommand extends Command
{
    protected $signature = 'reference-cache:flush';

    protected $description = 'Invalidate the cached age bands/settings, category tree, challenge types and content availability';

    public function handle(): int
    {
        ReferenceCache::flush(...ReferenceCache::ALL);
        $this->info('Reference caches invalidated: '.implode(', ', ReferenceCache::ALL).'.');

        return self::SUCCESS;
    }
}
