<?php

namespace App\Filament\Resources\ThemeWorlds;

use App\Filament\Resources\ThemeWorlds\Pages\ManageThemeWorlds;
use App\Modules\Policy\Models\AgeBand;
use App\Modules\Policy\Models\ThemeWorld;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The theme world catalog (docs/API.md "Worlds and unlocks"): order and unlock rule per world.
 * Every save, reorder and toggle invalidates the cached catalog (ReferenceDataBuilder). Worlds
 * are switched off rather than deleted; an inactive world is hidden and cannot be picked.
 */
class ThemeWorldResource extends Resource
{
    protected static ?string $model = ThemeWorld::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSwatch;

    protected static string|UnitEnum|null $navigationGroup = 'Policy';

    protected static ?string $navigationLabel = 'Theme worlds';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'key';

    public static function form(Schema $schema): Schema
    {
        $types = array_combine(ThemeWorld::UNLOCK_TYPES, ['Free', 'Lifetime points', 'Longest streak (days)', 'Family plan']);

        return $schema->columns(2)->components([
            Select::make('key')->label('World')->options(AgeBand::THEME_LABELS)->required()->unique(ignoreRecord: true),
            TextInput::make('sort')->integer()->minValue(0)->default(0)->required(),
            Select::make('unlock_type')->label('Unlocks with')->options($types)->required()->live(),
            TextInput::make('required')
                ->label('Required')
                ->helperText('Points or streak days needed')
                ->integer()
                ->minValue(1)
                ->maxValue(1_000_000)
                ->visible(fn (Get $get): bool => in_array($get('unlock_type'), ThemeWorld::THRESHOLD_TYPES, true))
                ->required(fn (Get $get): bool => in_array($get('unlock_type'), ThemeWorld::THRESHOLD_TYPES, true)),
            Toggle::make('active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->reorderable('sort')
            ->paginated(false)
            ->columns([
                TextColumn::make('key')->label('World')
                    ->formatStateUsing(fn (string $state): string => (AgeBand::THEME_LABELS[$state] ?? $state)." ({$state})"),
                TextColumn::make('unlock_type')->label('Unlocks with')->badge(),
                TextColumn::make('required')->placeholder('—'),
                ToggleColumn::make('active'),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageThemeWorlds::route('/'),
        ];
    }
}
