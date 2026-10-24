<?php

namespace App\Filament\Resources\Activities;

use App\Filament\Resources\Activities\Pages\ManageActivities;
use App\Filament\Support\AdminOnly;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Spatie\Activitylog\Models\Activity;
use UnitEnum;

/**
 * Audit log (spatie/laravel-activitylog): who changed what in the admin. Read-only.
 */
class ActivityResource extends Resource
{
    use AdminOnly;

    protected static ?string $model = Activity::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Audit log';

    protected static ?string $modelLabel = 'activity';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextEntry::make('description'),
            TextEntry::make('log_name'),
            TextEntry::make('subject_type'),
            TextEntry::make('subject_id'),
            TextEntry::make('causer.display_name')->label('By')->placeholder('system'),
            TextEntry::make('created_at')->dateTime(),
            TextEntry::make('attribute_changes')->columnSpanFull()
                ->formatStateUsing(fn (mixed $state) => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))
                ->fontFamily('mono'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->dateTime()->sortable(),
                TextColumn::make('log_name')->badge(),
                TextColumn::make('description'),
                TextColumn::make('subject_type')->formatStateUsing(fn (?string $state) => $state !== null ? class_basename($state) : '—'),
                TextColumn::make('subject_id'),
                TextColumn::make('causer.display_name')->label('By')->placeholder('system'),
            ])
            ->filters([
                SelectFilter::make('log_name')->options(['admin' => 'admin', 'content' => 'content', 'family' => 'family']),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageActivities::route('/'),
        ];
    }
}
