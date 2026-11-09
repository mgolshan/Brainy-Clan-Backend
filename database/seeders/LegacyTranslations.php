<?php

namespace Database\Seeders;

use RuntimeException;

/**
 * Loads and validates the optional French versions of the legacy English seed files
 * (data/multiple_choice_fr.php for data/multiple_choice.php, data/wisdom_cards_fr.php for
 * data/wisdom_cards.php), like data/countries_fr.php does for data/countries.php.
 *
 * Each file returns English key => French block, the key being the exact English question
 * (multiple choice) or card text (wisdom cards), i.e. the string the English item's identity is
 * derived from. The block has the question bank's locale shape (data/questions/README.md):
 * question|text/author/source, answer, wrong (3 or 2), explanation. The English rows keep their
 * source_ref and translation group; the French row joins that group.
 *
 * @phpstan-import-type LocaleBlock from QuestionBank
 */
class LegacyTranslations
{
    /** type => keys allowed in a French block */
    private const array FIELDS = [
        'multiple_choice' => ['question', 'answer', 'wrong', 'explanation'],
        'wisdom_card' => ['text', 'author', 'source', 'answer', 'wrong', 'explanation'],
    ];

    /**
     * @param  string  $path  the French file (missing file = no translations)
     * @param  string  $type  multiple_choice or wisdom_card
     * @param  string  $englishFile  the English file name, for messages
     * @param  array<string, array{count: int, has_explanation: bool, language_bound: bool}>  $english  English key => facts
     * @return array<string, LocaleBlock>
     */
    public static function load(string $path, string $type, string $englishFile, array $english): array
    {
        if (! is_file($path)) {
            return [];
        }

        $file = basename($path);
        $data = require $path;

        if (! is_array($data)) {
            throw new RuntimeException("{$file} must return an array of English key => French block.");
        }

        $out = [];

        foreach ($data as $key => $block) {
            $key = (string) $key;
            $fail = fn (string $message) => new RuntimeException("{$file}: [{$key}] {$message}");
            $facts = $english[$key] ?? throw $fail("is not an item in {$englishFile} (the key must be the exact English ".($type === 'wisdom_card' ? 'card text' : 'question').').');

            if ($facts['count'] > 1) {
                throw $fail("matches {$facts['count']} items in {$englishFile}; only unique English keys can be translated.");
            }

            if ($facts['language_bound']) {
                throw $fail('belongs to a language-bound category (English-only).');
            }

            if (is_array($block) && ($unknown = array_diff(array_keys($block), self::FIELDS[$type])) !== []) {
                throw $fail('has unknown field(s): '.implode(', ', $unknown).'.');
            }

            $parsed = QuestionBank::validateLocale($block, $type, 'fr', $fail);

            if ($facts['has_explanation'] && $parsed['explanation'] === null) {
                throw $fail('[fr.explanation] must be a non-empty string (the English item has one).');
            }

            $out[$key] = $parsed;
        }

        return $out;
    }
}
