<?php

namespace App\Modules\Chat\Support;

use Normalizer;

/**
 * The family chat's bad-words filter (docs/API.md "Chat safety"): masks every letter of a blocked
 * word or phrase with ★, so the length in code points stays the same. Word lists live in
 * `resources/chat-filter/{en,fr}.php` (`words`, plus `allow`: phrases that are never masked, such
 * as "cul-de-sac"); both languages always apply.
 *
 * Matching works on a folded copy of the text with exactly one code point per original code point
 * (lowercase, accents stripped; combining marks and zero-width characters become a separator), so
 * a match maps straight back onto the original text. A listed entry matches:
 *
 * - as a whole word or phrase only: never inside a longer word ("class", "Scunthorpe", "assassin");
 * - with common disguises: `@`/`4` → a, `3` → e, `1`/`!` → i, `0` → o, `$`/`5` → s, `7` → t;
 *   a letter repeated 3 or more times (`fuuuck`); separators inside the word (`f.u.c.k`, `f-u-c-k`,
 *   `f_u_c_k`, `f*u*c*k`) and single letters spaced out (`f u c k`).
 *
 * A single letter repeated twice does not count as a disguise, so "cul" never matches "cull". The
 * patterns are compiled once per process and run in small chunks (PCRE pattern size limits).
 */
final class ProfanityFilter
{
    public const string MASK = '★';

    /** @var list<string> */
    public const array LANGUAGES = ['en', 'fr'];

    /** Disguises: the characters that also stand for a letter. */
    private const array LEET = ['a' => '@4', 'e' => '3', 'i' => '1!', 'o' => '0', 's' => '$5', 't' => '7'];

    /** Separators allowed inside a word (regex class body). */
    private const string SEPARATORS = '._\-*~';

    /** Folded characters that are masked when they are part of a match (besides letters and digits). */
    private const string MASKED_SYMBOLS = '@$!';

    /** Entries per compiled pattern. */
    private const int CHUNK = 50;

    /** Folded-character memo size cap (per process). */
    private const int FOLD_MEMO_LIMIT = 5000;

    /** @var array{patterns: list<string>, allow: list<string>}|null */
    private static ?array $compiled = null;

    /** @var array<string, string> */
    private static array $foldMemo = [];

    /**
     * The text with blocked words masked, and whether anything was masked.
     *
     * @return array{text: string, filtered: bool}
     */
    public function mask(string $text): array
    {
        if ($text === '') {
            return ['text' => $text, 'filtered' => false];
        }

        $compiled = self::compiled();
        $original = mb_str_split($text);
        $folded = array_map(self::foldChar(...), $original);
        $subject = implode('', $folded);

        $allowed = [];

        foreach ($compiled['allow'] as $pattern) {
            array_push($allowed, ...self::spans($pattern, $subject));
        }

        $masked = false;

        foreach ($compiled['patterns'] as $pattern) {
            foreach (self::spans($pattern, $subject) as [$start, $end]) {
                if (self::insideAny($start, $end, $allowed)) {
                    continue;
                }

                for ($i = $start; $i < $end; $i++) {
                    if ($original[$i] !== self::MASK && self::isMaskable($folded[$i])) {
                        $original[$i] = self::MASK;
                        $masked = true;
                    }
                }
            }
        }

        return ['text' => $masked ? implode('', $original) : $text, 'filtered' => $masked];
    }

    /**
     * Forget the compiled patterns (tests that swap word lists).
     */
    public static function flush(): void
    {
        self::$compiled = null;
    }

    /**
     * @return array{patterns: list<string>, allow: list<string>}
     */
    private static function compiled(): array
    {
        if (self::$compiled !== null) {
            return self::$compiled;
        }

        $words = [];
        $allow = [];

        foreach (self::LANGUAGES as $language) {
            /** @var array{words?: list<string>, allow?: list<string>} $list */
            $list = require resource_path("chat-filter/{$language}.php");
            array_push($words, ...($list['words'] ?? []));
            array_push($allow, ...($list['allow'] ?? []));
        }

        $compact = [];
        $spaced = [];

        $entries = array_unique(array_map(self::foldEntry(...), $words));
        // Longest first, so "shithead" wins over "shit" inside one pattern.
        usort($entries, fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        foreach ($entries as $entry) {
            if ($entry === '') {
                continue;
            }

            $compact[] = self::compileEntry($entry);

            if (! str_contains($entry, ' ') && mb_strlen($entry) >= 3) {
                $spaced[] = implode('\h', array_map(self::letterClass(...), mb_str_split($entry)));
            }
        }

        $boundaryStart = '(?<![\p{L}\p{N}])';
        $boundaryEnd = '(?![\p{L}\p{N}])';
        // Spaced-out letters ("f u c k") must not be part of a longer run of single letters ("a s s e t").
        $spacedStart = $boundaryStart.'(?<!^[\p{L}\p{N}]\h)(?<![^\p{L}\p{N}][\p{L}\p{N}]\h)';
        $spacedEnd = $boundaryEnd.'(?!\h[\p{L}\p{N}](?![\p{L}\p{N}]))';

        $patterns = [];

        foreach (array_chunk($compact, self::CHUNK) as $chunk) {
            $patterns[] = '/'.$boundaryStart.'(?:'.implode('|', $chunk).')'.$boundaryEnd.'/u';
        }

        foreach (array_chunk($spaced, self::CHUNK) as $chunk) {
            $patterns[] = '/'.$spacedStart.'(?:'.implode('|', $chunk).')'.$spacedEnd.'/u';
        }

        $allowPatterns = [];
        $allowEntries = array_map(
            fn (string $entry): string => implode('[\s'.self::SEPARATORS.']+', array_map(fn (string $char): string => preg_quote($char, '/'), self::words($entry))),
            array_values(array_unique(array_filter(array_map(self::foldEntry(...), $allow)))),
        );

        foreach (array_chunk($allowEntries, self::CHUNK) as $chunk) {
            $allowPatterns[] = '/'.$boundaryStart.'(?:'.implode('|', $chunk).')'.$boundaryEnd.'/u';
        }

        return self::$compiled = ['patterns' => $patterns, 'allow' => $allowPatterns];
    }

    /**
     * A word or phrase: each word's letters (with disguises, repeats and inner separators), words
     * joined by whitespace or separators.
     */
    private static function compileEntry(string $entry): string
    {
        return implode('[\s'.self::SEPARATORS.']+', array_map(self::compileWord(...), self::words($entry)));
    }

    private static function compileWord(string $word): string
    {
        $units = [];
        $chars = mb_str_split($word);
        $count = count($chars);

        $i = 0;

        while ($i < $count) {
            $run = 1;

            while ($i + $run < $count && $chars[$i + $run] === $chars[$i]) {
                $run++;
            }

            $class = self::letterClass($chars[$i]);
            // A single letter: once, or 3+ times ("fuuuck"; twice is a different word: "cul" ≠ "cull").
            $units[] = $run === 1 ? "{$class}(?:{$class}{2,})?" : "{$class}{{$run},}";
            $i += $run;
        }

        return implode('['.self::SEPARATORS.']*', $units);
    }

    private static function letterClass(string $char): string
    {
        $alternatives = self::LEET[$char] ?? '';

        return $alternatives === '' ? preg_quote($char, '/') : '['.preg_quote($char.$alternatives, '/').']';
    }

    /**
     * @return list<string>
     */
    private static function words(string $entry): array
    {
        return array_values(array_filter(preg_split('/[\s\-]+/u', $entry) ?: [], fn (string $word): bool => $word !== ''));
    }

    private static function foldEntry(string $entry): string
    {
        $folded = implode('', array_map(self::foldChar(...), mb_str_split(trim($entry))));

        return trim((string) preg_replace('/\s+/u', ' ', $folded));
    }

    /**
     * One code point in, one code point out: lowercase without accents; combining marks and
     * zero-width/format characters become "." (a separator), so "f\u{200B}uck" and strike-through
     * letters still match.
     */
    private static function foldChar(string $char): string
    {
        if (isset(self::$foldMemo[$char])) {
            return self::$foldMemo[$char];
        }

        if (preg_match('/^[\p{Mn}\p{Me}\p{Cf}]$/u', $char) === 1) {
            $folded = '.';
        } else {
            $lower = mb_strtolower($char);
            $lower = mb_strlen($lower) === 1 ? $lower : $char;
            $decomposed = Normalizer::normalize($lower, Normalizer::FORM_D);
            $base = is_string($decomposed) ? (string) preg_replace('/[\p{Mn}\p{Me}]/u', '', $decomposed) : $lower;
            $folded = mb_strlen($base) === 1 ? $base : $lower;
        }

        if (count(self::$foldMemo) < self::FOLD_MEMO_LIMIT) {
            self::$foldMemo[$char] = $folded;
        }

        return $folded;
    }

    private static function isMaskable(string $folded): bool
    {
        return str_contains(self::MASKED_SYMBOLS, $folded) || preg_match('/^[\p{L}\p{N}]$/u', $folded) === 1;
    }

    /**
     * Matches as [start, end) code point offsets.
     *
     * @return list<array{0: int, 1: int}>
     */
    private static function spans(string $pattern, string $subject): array
    {
        if (preg_match_all($pattern, $subject, $matches, PREG_OFFSET_CAPTURE) === false || $matches[0] === []) {
            return [];
        }

        $spans = [];

        foreach ($matches[0] as [$match, $offset]) {
            $start = mb_strlen(substr($subject, 0, $offset));
            $spans[] = [$start, $start + mb_strlen($match)];
        }

        return $spans;
    }

    /**
     * @param  list<array{0: int, 1: int}>  $spans
     */
    private static function insideAny(int $start, int $end, array $spans): bool
    {
        foreach ($spans as [$from, $to]) {
            if ($start >= $from && $end <= $to) {
                return true;
            }
        }

        return false;
    }
}
