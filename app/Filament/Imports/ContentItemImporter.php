<?php

namespace App\Filament\Imports;

use App\Modules\Content\Actions\ImportContentRow;
use App\Modules\Content\Models\ContentItem;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;

/**
 * CSV import for content items. The row is handed to the ImportContentRow action, which asks the
 * challenge type plugin to build and validate payload + answer; invalid rows end up in the
 * "failed rows" download with the reason.
 *
 * Columns: category_slug, type_key, difficulty, min_age, max_age, locale, status, tags (a;b),
 * source_ref, question, choice_a … choice_f, correct (a-f), explanation, image_url,
 * card_text, card_author, card_source (wisdom cards).
 */
class ContentItemImporter extends Importer
{
    protected static ?string $model = ContentItem::class;

    public static function getColumns(): array
    {
        $optional = [
            'difficulty' => '3', 'min_age' => '7', 'max_age' => '', 'locale' => 'en', 'status' => 'draft', 'tags' => 'canada;easy-read',
            'source_ref' => 'capitals-2026-001', 'question' => 'What is the capital of Peru?', 'choice_a' => 'Lima', 'choice_b' => 'Quito',
            'choice_c' => 'Bogotá', 'choice_d' => 'Santiago', 'choice_e' => '', 'choice_f' => '', 'correct' => 'a', 'explanation' => '',
            'image_url' => '', 'card_text' => '', 'card_author' => '', 'card_source' => '',
            'translation_group' => '', 'cross_locale' => '',
        ];

        $columns = [
            ImportColumn::make('category_slug')->requiredMapping()->example('capitals-americas')->fillRecordUsing(fn () => null),
            ImportColumn::make('type_key')->requiredMapping()->example('multiple_choice')->fillRecordUsing(fn () => null),
        ];

        foreach ($optional as $name => $example) {
            $columns[] = ImportColumn::make($name)->example($example)->fillRecordUsing(fn () => null);
        }

        return $columns;
    }

    public function resolveRecord(): ContentItem
    {
        return new ContentItem;
    }

    public function fillRecord(): void
    {
        // Filled by ImportContentRow in saveRecord().
    }

    public function saveRecord(): void
    {
        $this->record = app(ImportContentRow::class)->handle($this->data);
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your content import has completed and '.Number::format($import->successful_rows).' '.str('row')->plural($import->successful_rows).' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to import.';
        }

        return $body;
    }
}
