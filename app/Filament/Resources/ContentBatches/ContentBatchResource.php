<?php

namespace App\Filament\Resources\ContentBatches;

use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\ContentBatches\Pages\CreateContentBatch;
use App\Filament\Resources\ContentBatches\Pages\EditContentBatch;
use App\Filament\Resources\ContentBatches\Pages\ListContentBatches;
use App\Filament\Resources\ContentBatches\Pages\ViewContentBatch;
use App\Modules\ChallengeTypes\ChallengeTypeRegistry;
use App\Modules\Content\Models\ContentBatch;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Generation / import runs. The AI batch generator page arrives in B7; for now batches can be
 * recorded by hand and their items reviewed in Content items.
 */
class ContentBatchResource extends Resource
{
    protected static ?string $model = ContentBatch::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 6;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('category_id')->label('Category')->options(fn () => CategoryResource::parentOptions())->searchable(),
            Select::make('type_key')->label('Type')->required()
                ->options(fn () => collect(app(ChallengeTypeRegistry::class)->handlers())->mapWithKeys(fn ($c, $k) => [$k => $k])->all()),
            TextInput::make('model')->maxLength(80),
            Select::make('status')->options(array_combine(ContentBatch::STATUSES, ContentBatch::STATUSES))->default('draft')->required(),
            TextInput::make('item_count')->numeric()->default(0),
            Textarea::make('prompt')->rows(6)->columnSpanFull(),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            TextEntry::make('category.slug')->label('Category'),
            TextEntry::make('type_key'),
            TextEntry::make('status')->badge(),
            TextEntry::make('model'),
            TextEntry::make('item_count'),
            TextEntry::make('items_count')->label('Items in DB')->state(fn (ContentBatch $record) => $record->items()->count()),
            TextEntry::make('prompt')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('category.slug')->label('Category'),
                TextColumn::make('type_key')->badge(),
                TextColumn::make('status')->badge(),
                TextColumn::make('item_count'),
                TextColumn::make('items_count')->counts('items')->label('Items in DB'),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->recordActions([ViewAction::make(), EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContentBatches::route('/'),
            'create' => CreateContentBatch::route('/create'),
            'view' => ViewContentBatch::route('/{record}'),
            'edit' => EditContentBatch::route('/{record}/edit'),
        ];
    }
}
