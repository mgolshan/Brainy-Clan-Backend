<?php

namespace App\Modules\Identity\Legal;

use App\Modules\Core\Support\Website;
use App\Modules\Identity\Models\User;

/**
 * The current legal documents from `config/legal.php`: versions, localized "what changed"
 * summaries and the public page URLs on the website (`/terms`, `/privacy`).
 */
final class LegalDocuments
{
    public const string TERMS = 'terms';

    public const string PRIVACY = 'privacy';

    public const array DOCUMENTS = [self::TERMS, self::PRIVACY];

    public static function version(string $document): string
    {
        return (string) config("legal.documents.{$document}.version");
    }

    public static function termsVersion(): string
    {
        return self::version(self::TERMS);
    }

    public static function privacyVersion(): string
    {
        return self::version(self::PRIVACY);
    }

    public static function summary(string $document, string $locale): ?string
    {
        $summary = config("legal.documents.{$document}.summary.{$locale}") ?? config("legal.documents.{$document}.summary.en");

        return is_string($summary) && $summary !== '' ? $summary : null;
    }

    /**
     * Absolute URL of a document's page on the website host; French gets `?lang=fr`.
     */
    public static function url(string $document, string $locale = 'en'): string
    {
        return Website::route("legal.{$document}", $locale === 'en' ? [] : ['lang' => $locale]);
    }

    public static function isDraft(): bool
    {
        return (bool) config('legal.draft');
    }

    /**
     * `en` unless a supported locale is asked for (anything else falls back to English).
     */
    public static function locale(mixed $requested): string
    {
        return is_string($requested) && in_array($requested, User::LOCALES, true) ? $requested : 'en';
    }

    /**
     * `GET /legal` payload.
     *
     * @return array<string, array{version: string, url: string, summary: string|null}>
     */
    public static function describe(string $locale): array
    {
        $documents = [];

        foreach (self::DOCUMENTS as $document) {
            $documents[$document] = [
                'version' => self::version($document),
                'url' => self::url($document, $locale),
                'summary' => self::summary($document, $locale),
            ];
        }

        return $documents;
    }
}
