<?php

namespace App\Modules\Family\Support;

use App\Modules\Family\Actions\BuildClassroomSummary;

/**
 * The class summary as CSV (docs/API.md "Classroom tools" → "Class summary export"): UTF-8 with a
 * BOM (so spreadsheet apps read accents), a header row in the requester's locale (en/fr,
 * {@see self::HEADERS}), then the summary rows in `BuildClassroomSummary::COLUMNS` order.
 * A text cell that starts with `=`, `+`, `-`, `@`, a tab or a carriage return gets a leading `'`,
 * so a spreadsheet never runs a nickname as a formula.
 */
final class ClassroomSummaryCsv
{
    /** Column headers per locale, keyed like `BuildClassroomSummary::COLUMNS`. */
    public const array HEADERS = [
        'en' => [
            'member_id' => 'Member ID',
            'nickname' => 'Nickname',
            'role' => 'Role',
            'points' => 'Points',
            'games' => 'Games',
            'accuracy_percent' => 'Accuracy (%)',
            'current_streak' => 'Current streak (days)',
            'longest_streak' => 'Longest streak (days)',
            'last_played_at' => 'Last played (UTC)',
        ],
        'fr' => [
            'member_id' => 'ID du membre',
            'nickname' => 'Surnom',
            'role' => 'Rôle',
            'points' => 'Points',
            'games' => 'Parties',
            'accuracy_percent' => 'Précision (%)',
            'current_streak' => 'Série actuelle (jours)',
            'longest_streak' => 'Plus longue série (jours)',
            'last_played_at' => 'Dernière partie (UTC)',
        ],
    ];

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public static function render(array $rows, ?string $locale): string
    {
        $locale = $locale === 'fr' ? 'fr' : 'en';
        $out = fopen('php://temp', 'w+b');

        if ($out === false) {
            return '';
        }

        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, array_map(fn (string $column): string => self::HEADERS[$locale][$column], BuildClassroomSummary::COLUMNS), escape: '');

        foreach ($rows as $row) {
            fputcsv($out, array_map(fn (string $column): string => self::cell($row[$column] ?? null), BuildClassroomSummary::COLUMNS), escape: '');
        }

        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    private static function cell(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (! is_string($value)) {
            return is_scalar($value) ? (string) $value : '';
        }

        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }
}
