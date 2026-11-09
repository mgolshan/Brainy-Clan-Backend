<?php

namespace App\Modules\Support\Support;

use App\Modules\Chat\Support\ChatText;

/**
 * Support message text (docs/API.md "Support messages"): plain UTF-8 text, never HTML or
 * Markdown. Normalized like chat text ({@see ChatText::normalize()}: NFC, CRLF → LF, control
 * characters removed, at most 2 empty lines in a row, trimmed); length counted in Unicode code
 * points: messages {@see self::MIN_LENGTH}–{@see self::MAX_LENGTH}.
 *
 * Single-line fields (the website form's name, the app's device/app version) go through
 * {@see self::singleLine()}: every line break and control character is removed, so they can never
 * inject a mail header.
 */
final class SupportText
{
    public const int MIN_LENGTH = 10;

    public const int MAX_LENGTH = 5000;

    /** Raw input cap before normalization (keeps the work bounded; checked by the Form Requests). */
    public const int MAX_RAW_LENGTH = 20000;

    /** Staff replies: 1–10000 code points. */
    public const int REPLY_MAX_LENGTH = 10000;

    /**
     * The normalized text, or null when the input is not valid UTF-8.
     */
    public static function normalize(string $text): ?string
    {
        return ChatText::normalize($text);
    }

    /**
     * Whether a normalized message may be stored: 10–5000 code points.
     */
    public static function isValidLength(string $normalized): bool
    {
        $length = mb_strlen($normalized);

        return $length >= self::MIN_LENGTH && $length <= self::MAX_LENGTH;
    }

    /**
     * One line of plain text: NFC, every control character (CR, LF, tab, NUL, …) and the Unicode
     * line/paragraph separators replaced by a space, spaces collapsed, trimmed. Null for invalid
     * UTF-8.
     */
    public static function singleLine(string $text): ?string
    {
        $text = ChatText::normalize($text);

        if ($text === null) {
            return null;
        }

        $text = (string) preg_replace('/[\p{Cc}\x{2028}\x{2029}]+/u', ' ', $text);

        return trim((string) preg_replace('/\s{2,}/u', ' ', $text));
    }
}
