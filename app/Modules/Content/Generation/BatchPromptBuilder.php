<?php

namespace App\Modules\Content\Generation;

use App\Modules\Content\Models\ContentBatch;
use App\Modules\Content\Models\ContentItem;
use App\Modules\Content\Support\CategoryTree;

/**
 * Builds the system instructions (content rules, stable across batches so they can be cached)
 * and the user prompt (category path, type, ranges, count, admin note, items not to repeat).
 */
class BatchPromptBuilder
{
    public const string CONTENT_RULES = <<<'TXT'
        You write quiz content for Brainy Clan, a family learning app played by children, teens, adults and seniors together.

        Content rules (all items, no exceptions):
        - Age-appropriate for the requested age range: no violence, gore, sexual content, drugs, gambling, politics, religion as fact, or frightening themes; plain vocabulary for young ages.
        - Factually correct and uncontroversial. Only use facts you are certain of and that do not change quickly (avoid "current" records, prices, office holders). If unsure, choose another fact.
        - Every item has a short explanation that teaches something.
        - Copyright: never quote modern or living authors, song lyrics, films, TV shows or brands; never summarize books. Quotes are only allowed from authors who died long ago (public domain), verbatim, with author and source. Otherwise write original text.
        - No personal data, no real private people, no links.
        - Inclusive and respectful of all cultures; avoid stereotypes.
        - Each item stands alone and is different from the others (no near-duplicates, no repeated answers across items where avoidable).
        - Write in the requested language only.
        TXT;

    public function __construct(
        private readonly CategoryTree $tree,
        private readonly GenerationTypes $types,
    ) {}

    public function instructions(): string
    {
        return self::CONTENT_RULES;
    }

    public function prompt(ContentBatch $batch): string
    {
        $key = GenerationTypes::key($batch->type_key, $batch->variant);
        $path = $batch->category_id !== null ? implode(' → ', $this->tree->pathNames($batch->category_id)) : 'General knowledge';
        $ages = ($batch->min_age ?? 5).'–'.($batch->max_age ?? 99);
        $language = match ($batch->locale) {
            'fr' => 'French (fr)',
            default => 'English (en)',
        };

        $lines = [
            "Create {$batch->requested_count} items.",
            '',
            "Category: {$path}",
            'Type: '.GenerationTypes::LABELS[$key],
            "Difficulty: {$batch->min_difficulty} to {$batch->max_difficulty} on a 1–10 scale; spread items across this range.",
            "Players' ages: {$ages}",
            "Language: {$language}",
            '',
            $this->types->guidance($key),
        ];

        if (filled($batch->note)) {
            $lines[] = '';
            $lines[] = 'Note from the editor: '.trim((string) $batch->note);
        }

        $avoid = $this->existingTexts($batch);

        if ($avoid !== []) {
            $lines[] = '';
            $lines[] = 'These already exist in this category; do not repeat them or ask about the same fact:';

            foreach ($avoid as $text) {
                $lines[] = '- '.$text;
            }
        }

        $lines[] = '';
        $lines[] = 'Return exactly the JSON object described by the schema: {"items": [...]}.';

        return implode("\n", $lines);
    }

    /**
     * Recent items of the same type in the category subtree, as short texts.
     *
     * @return list<string>
     */
    private function existingTexts(ContentBatch $batch): array
    {
        if ($batch->category_id === null) {
            return [];
        }

        return ContentItem::query()
            ->whereIn('category_id', $this->tree->subtreeIds($batch->category_id))
            ->where('type_key', $batch->type_key)
            ->where('locale', $batch->locale)
            ->when($batch->variant !== null, fn ($query) => $query->where('payload->variant', $batch->variant))
            ->where('status', '!=', ContentItem::STATUS_ARCHIVED)
            ->latest('id')
            ->limit((int) config('content_generator.avoid_examples', 40))
            ->get(['id', 'type_key', 'payload', 'answer'])
            ->map(fn (ContentItem $item) => mb_strimwidth(ContentFingerprint::text($item->type_key, $item->payload, $item->answer), 0, 140, '…'))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
