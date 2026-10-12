<?php

namespace App\Modules\Content\Generation;

use App\Modules\ChallengeTypes\Support\Words;

/**
 * What makes two items "the same" for de-duplication: the normalized question (multiple
 * choice), card text (wisdom cards), answer word (anagram / missing letter) or sorted word
 * list (word search). Normalized = Unicode-folded lower case, punctuation removed, single spaces.
 */
class ContentFingerprint
{
    /**
     * The human-readable text the fingerprint is based on (also used in the prompt's
     * "do not repeat" list).
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $answer
     */
    public static function text(string $typeKey, array $payload, array $answer): string
    {
        $variant = $payload['variant'] ?? null;

        return match (true) {
            $typeKey === 'wisdom_card' => (string) ($payload['card']['text'] ?? ''),
            $typeKey === 'word_puzzle' && $variant === 'word_search' => self::sortedWords((array) ($payload['words'] ?? [])),
            $typeKey === 'word_puzzle' => (string) ($answer['word'] ?? ((array) ($answer['words'] ?? []))[0] ?? ''),
            default => (string) ($payload['question'] ?? ''),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $answer
     */
    public static function of(string $typeKey, array $payload, array $answer): ?string
    {
        $normalized = self::normalize(self::text($typeKey, $payload, $answer));

        if ($normalized === '') {
            return null;
        }

        $variant = is_string($payload['variant'] ?? null) ? ':'.$payload['variant'] : '';

        return $typeKey.$variant.'|'.$normalized;
    }

    /**
     * Stable `source_ref` for an AI item (the unique index on source_ref is the last guard).
     */
    public static function sourceRef(string $fingerprint, string $locale): string
    {
        return 'ai:'.$locale.':'.sha1($fingerprint);
    }

    public static function normalize(string $text): string
    {
        $folded = Words::fold($text);
        $letters = (string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $folded);

        return trim((string) preg_replace('/\s+/u', ' ', $letters));
    }

    /**
     * @param  array<int, mixed>  $words
     */
    private static function sortedWords(array $words): string
    {
        $words = array_map(fn ($w) => Words::fold((string) $w), $words);
        sort($words);

        return implode(' ', $words);
    }
}
