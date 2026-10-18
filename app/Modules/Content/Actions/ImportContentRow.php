<?php

namespace App\Modules\Content\Actions;

use App\Modules\ChallengeTypes\ChallengeTypeRegistry;
use App\Modules\Content\Models\Category;
use App\Modules\Content\Models\ContentItem;
use Illuminate\Validation\ValidationException;

/**
 * Turns one flat CSV row into a content item (used by the Filament CSV importer).
 *
 * Common columns: category_slug, type_key, difficulty, min_age, max_age, locale, status,
 * tags (separated by ";"), source_ref (optional; re-importing the same ref updates the item),
 * translation_group and cross_locale (optional, 0/1; empty cells keep the stored value).
 * Type-specific columns come from the plugin's flatColumns().
 */
class ImportContentRow
{
    public function __construct(
        private readonly ChallengeTypeRegistry $registry,
        private readonly SaveContentItem $save,
        private readonly TagContentItems $tag,
    ) {}

    /**
     * @param  array<string, mixed>  $row
     *
     * @throws ValidationException
     */
    public function handle(array $row, ?int $batchId = null): ContentItem
    {
        $typeKey = trim((string) ($row['type_key'] ?? ''));

        if (! $this->registry->has($typeKey)) {
            throw ValidationException::withMessages(['type_key' => "Unknown challenge type [{$typeKey}]."]);
        }

        $category = Category::query()->where('slug', trim((string) ($row['category_slug'] ?? '')))->first();

        if ($category === null) {
            throw ValidationException::withMessages(['category_slug' => 'Unknown category slug.']);
        }

        $built = $this->registry->get($typeKey)->fromFlatRow($row);
        $sourceRef = trim((string) ($row['source_ref'] ?? ''));
        $status = trim((string) ($row['status'] ?? '')) ?: ContentItem::STATUS_DRAFT;

        if (! in_array($status, ContentItem::STATUSES, true)) {
            throw ValidationException::withMessages(['status' => 'Status must be one of: '.implode(', ', ContentItem::STATUSES).'.']);
        }

        $existing = $sourceRef !== '' ? ContentItem::query()->where('source_ref', $sourceRef)->first() : null;

        $translation = [];

        if (trim((string) ($row['translation_group'] ?? '')) !== '') {
            $translation['translation_group'] = trim((string) $row['translation_group']);
        }

        if (trim((string) ($row['cross_locale'] ?? '')) !== '') {
            $translation['cross_locale'] = filter_var($row['cross_locale'], FILTER_VALIDATE_BOOLEAN);
        }

        $item = $this->save->handle([
            'category_id' => $category->id,
            'type_key' => $typeKey,
            'locale' => trim((string) ($row['locale'] ?? '')) ?: 'en',
            'difficulty' => (int) ($row['difficulty'] ?? 3),
            'min_age' => self::nullableInt($row['min_age'] ?? null),
            'max_age' => self::nullableInt($row['max_age'] ?? null),
            'payload' => $built['payload'],
            'answer' => $built['answer'],
            'status' => $status,
            'source' => 'csv',
            'source_ref' => $sourceRef !== '' ? $sourceRef : null,
            'content_batch_id' => $batchId,
            ...$translation,
        ], $existing);

        $tags = array_filter(array_map('trim', explode(';', (string) ($row['tags'] ?? ''))));

        if ($tags !== []) {
            $this->tag->handle([$item], array_values($tags));
        }

        return $item;
    }

    private static function nullableInt(mixed $value): ?int
    {
        return $value === null || trim((string) $value) === '' ? null : (int) $value;
    }
}
