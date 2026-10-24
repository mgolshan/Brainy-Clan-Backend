<?php

namespace App\Modules\Chat\Support;

use Normalizer;

/**
 * Family chat message text (docs/API.md "Family chat"): plain UTF-8 text, never HTML. The server
 * only normalizes — it never escapes, decodes, parses or renders (JSON encoding does the rest and
 * clients render plain text):
 *
 * 1. Unicode NFC; invalid UTF-8 is rejected (null).
 * 2. `\r\n` / `\r` become `\n`; every other control character (Unicode `Cc`: NUL, tab, ESC, C1…)
 *    is removed. Emoji joiners, variation selectors, skin tones and flag tags are not controls and stay.
 * 3. More than 2 consecutive empty lines collapse to 2.
 * 4. Leading/trailing whitespace is trimmed.
 *
 * Length is counted in Unicode code points (`mb_strlen`): 1–{@see self::MAX_LENGTH}.
 */
final class ChatText
{
    public const int MAX_LENGTH = 1000;

    /** Raw input cap before normalization (keeps the work bounded; checked by the Form Request). */
    public const int MAX_RAW_LENGTH = 5000;

    /**
     * The normalized text, or null when the input is not valid UTF-8.
     */
    public static function normalize(string $text): ?string
    {
        if (! mb_check_encoding($text, 'UTF-8')) {
            return null;
        }

        $text = Normalizer::normalize($text, Normalizer::FORM_C);

        if (! is_string($text)) {
            return null;
        }

        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = (string) preg_replace('/(?!\n)\p{Cc}/u', '', $text);
        $text = (string) preg_replace('/\n(?:[^\S\n]*\n){3,}/u', "\n\n\n", $text);

        return (string) preg_replace('/^\s+|\s+$/u', '', $text);
    }

    /**
     * Whether normalized text may be stored: 1–1000 code points.
     */
    public static function isValidLength(string $normalized): bool
    {
        $length = mb_strlen($normalized);

        return $length >= 1 && $length <= self::MAX_LENGTH;
    }
}
