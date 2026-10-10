<?php

namespace App\Filament\Support;

use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\CodeEditor\Enums\Language;

/**
 * A JSON code editor bound to a JSON/array-cast attribute: shows pretty JSON, validates it
 * and stores the decoded value.
 */
final class JsonEditor
{
    public static function make(string $name): CodeEditor
    {
        return CodeEditor::make($name)
            ->language(Language::Json)
            ->formatStateUsing(fn (mixed $state): string => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: 'null')
            ->dehydrateStateUsing(fn (?string $state): mixed => $state === null || trim($state) === '' ? null : json_decode($state, true))
            ->rule('json');
    }
}
