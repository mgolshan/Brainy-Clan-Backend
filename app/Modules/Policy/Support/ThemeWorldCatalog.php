<?php

namespace App\Modules\Policy\Support;

use App\Modules\Core\Support\ReferenceCache;
use App\Modules\Policy\Models\ThemeWorld;

/**
 * The active theme worlds in display order, from ReferenceCache (no query on a warm cache).
 * Scoped per request / job; every ThemeWorld write flushes it (ReferenceDataBuilder).
 */
class ThemeWorldCatalog
{
    /** @var array<string, array{key: string, unlock_type: string, required: int|null}>|null */
    private ?array $worlds = null;

    /**
     * Active worlds keyed by theme key, in display order.
     *
     * @return array<string, array{key: string, unlock_type: string, required: int|null}>
     */
    public function all(): array
    {
        if ($this->worlds !== null) {
            return $this->worlds;
        }

        /** @var array<string, array{key: string, unlock_type: string, required: int|null}> $worlds */
        $worlds = ReferenceCache::remember(ReferenceCache::THEME_WORLDS, fn (): array => ThemeWorld::query()
            ->where('active', true)
            ->orderBy('sort')
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (ThemeWorld $world): array => [$world->key => [
                'key' => $world->key,
                'unlock_type' => $world->unlock_type,
                'required' => $world->required,
            ]])
            ->all());

        return $this->worlds = $worlds;
    }

    /**
     * The world for a theme key, or null when it is unknown or inactive.
     *
     * @return array{key: string, unlock_type: string, required: int|null}|null
     */
    public function find(string $key): ?array
    {
        return $this->all()[$key] ?? null;
    }

    public static function flushCache(): void
    {
        ReferenceCache::flush(ReferenceCache::THEME_WORLDS);

        if (app()->resolved(self::class)) {
            app(self::class)->worlds = null;
        }
    }
}
