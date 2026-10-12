<?php

namespace App\Modules\ChallengeTypes\Support;

use Normalizer;

/**
 * Unicode-aware word handling for the word puzzles: NFC normalization (so "é" typed as e + ◌́
 * equals "é") plus multibyte case folding, so French accents compare correctly.
 */
final class Words
{
    /**
     * Comparison key: trimmed, NFC-normalized, case-folded.
     */
    public static function fold(string $word): string
    {
        return mb_convert_case(self::nfc(trim($word)), MB_CASE_FOLD);
    }

    /**
     * Display form: trimmed, NFC-normalized, uppercase.
     */
    public static function display(string $word): string
    {
        return mb_strtoupper(self::nfc(trim($word)));
    }

    public static function equals(string $a, string $b): bool
    {
        return self::fold($a) === self::fold($b);
    }

    /**
     * @return list<string>
     */
    public static function letters(string $word): array
    {
        return mb_str_split(self::nfc($word));
    }

    private static function nfc(string $text): string
    {
        $normalized = Normalizer::normalize($text, Normalizer::FORM_C);

        return is_string($normalized) ? $normalized : $text;
    }
}
