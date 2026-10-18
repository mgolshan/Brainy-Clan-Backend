<?php

namespace App\Modules\Content\Support;

use App\Modules\Content\Models\ContentItem;
use App\Modules\Core\Support\ReferenceCache;

/**
 * Which challenge types have published content in which category, for a member's age and
 * locale (the `challenge_types` of `GET /categories`). The distinct
 * (category, type, locale, cross_locale, min_age, max_age) combinations of published items are
 * loaded once per request/job and cached (ReferenceCache::CONTENT_AVAILABILITY) until any
 * content item is written; age, locale and active-type filters run in PHP with exactly the
 * rules of ContentItem::whereAvailableInLocale (without translation dedupe) and forAge.
 */
class ContentAvailability
{
    /** @var list<array{0: int, 1: string, 2: string, 3: bool, 4: int|null, 5: int|null}>|null */
    private ?array $rows = null;

    public static function flush(): void
    {
        ReferenceCache::flush(ReferenceCache::CONTENT_AVAILABILITY);

        if (app()->resolved(self::class)) {
            app(self::class)->rows = null;
        }
    }

    /**
     * @param  list<string>  $typeKeys  only these challenge types (the active ones)
     * @return array<int, list<string>> category id => type keys with playable content
     */
    public function typesByCategory(?int $age, string $locale, array $typeKeys): array
    {
        $allowed = array_flip($typeKeys);
        $map = [];

        foreach ($this->rows() as [$categoryId, $type, $itemLocale, $crossLocale, $minAge, $maxAge]) {
            if (! isset($allowed[$type]) || ! self::playableInLocale($itemLocale, $crossLocale, $locale)) {
                continue;
            }

            if ($age !== null && (($minAge !== null && $minAge > $age) || ($maxAge !== null && $maxAge < $age))) {
                continue;
            }

            $map[$categoryId][$type] = true;
        }

        return array_map(fn (array $types): array => array_keys($types), $map);
    }

    /**
     * English members play English items; others play their locale plus cross-locale English.
     */
    private static function playableInLocale(string $itemLocale, bool $crossLocale, string $memberLocale): bool
    {
        if ($memberLocale === 'en') {
            return $itemLocale === 'en';
        }

        return $itemLocale === $memberLocale || ($itemLocale === 'en' && $crossLocale);
    }

    /**
     * @return list<array{0: int, 1: string, 2: string, 3: bool, 4: int|null, 5: int|null}>
     */
    private function rows(): array
    {
        if ($this->rows !== null) {
            return $this->rows;
        }

        /** @var list<array{0: int, 1: string, 2: string, 3: bool, 4: int|null, 5: int|null}> $rows */
        $rows = ReferenceCache::remember(ReferenceCache::CONTENT_AVAILABILITY, fn (): array => ContentItem::query()
            ->published()
            ->toBase()
            ->distinct()
            ->get(['category_id', 'type_key', 'locale', 'cross_locale', 'min_age', 'max_age'])
            ->map(fn (object $row): array => [
                (int) $row->category_id,
                (string) $row->type_key,
                (string) $row->locale,
                (bool) $row->cross_locale,
                $row->min_age !== null ? (int) $row->min_age : null,
                $row->max_age !== null ? (int) $row->max_age : null,
            ])
            ->values()
            ->all());

        return $this->rows = $rows;
    }
}
