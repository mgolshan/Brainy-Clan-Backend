<?php

namespace App\Filament\Resources\ChallengeTypes;

use App\Filament\Resources\ChallengeTypes\Pages\ManageChallengeTypes;
use App\Filament\Support\JsonEditor;
use App\Modules\ChallengeTypes\Contracts\ChallengeType as ChallengeTypeContract;
use App\Modules\Content\Models\ChallengeType;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Challenge type plugins. Switching a type off hides its items from new sessions immediately.
 */
class ChallengeTypeResource extends Resource
{
    protected static ?string $model = ChallengeType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPuzzlePiece;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        $handlers = collect((array) config('challenge_types.handlers'))
            ->filter(fn ($class) => is_a($class, ChallengeTypeContract::class, true))
            ->mapWithKeys(fn ($class) => [$class => class_basename($class)])
            ->all();

        return $schema->columns(2)->components([
            TextInput::make('key')->required()->maxLength(40)->alphaDash()->unique(ignoreRecord: true),
            TextInput::make('name')->required()->maxLength(80),
            Select::make('handler_class')->options($handlers)->required(),
            Toggle::make('is_active')->default(true),
            JsonEditor::make('config')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('key')->fontFamily('mono'),
                TextColumn::make('name'),
                TextColumn::make('handler_class')->formatStateUsing(fn (string $state) => class_basename($state)),
                ToggleColumn::make('is_active')->label('Active'),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageChallengeTypes::route('/'),
        ];
    }
}
