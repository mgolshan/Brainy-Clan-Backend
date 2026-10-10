<?php

namespace App\Filament\Resources\Settings;

use App\Filament\Resources\Settings\Pages\ManageSettings;
use App\Filament\Support\JsonEditor;
use App\Modules\Family\Models\Family;
use App\Modules\Policy\Models\Setting;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Feature flags, limits and remote config. Most specific scope wins: age band > tier > global.
 */
class SettingResource extends Resource
{
    protected static ?string $model = Setting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|UnitEnum|null $navigationGroup = 'Policy';

    protected static ?string $navigationLabel = 'Settings & flags';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'key';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            TextInput::make('key')->required()->maxLength(100)->helperText('e.g. limits.max_members, play.target_points, feature_flags'),
            Select::make('scope')->options(array_combine(Setting::SCOPES, Setting::SCOPES))->default(Setting::SCOPE_GLOBAL)->required()->live(),
            TextInput::make('scope_id')
                ->label('Scope id')
                ->helperText(fn (Get $get) => match ($get('scope')) {
                    Setting::SCOPE_TIER => 'Tier: '.implode(', ', Family::TIERS),
                    Setting::SCOPE_AGE_BAND => 'Age band code, e.g. child',
                    default => 'Leave empty for global',
                })
                ->required(fn (Get $get) => $get('scope') !== Setting::SCOPE_GLOBAL),
            JsonEditor::make('value')->columnSpanFull()->helperText('JSON: 10, true, "1.0.0" or {"flag": true}'),
            TextInput::make('description')->columnSpanFull()->maxLength(255),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('key')
            ->columns([
                TextColumn::make('key')->searchable()->sortable()->fontFamily('mono'),
                TextColumn::make('scope')->badge()->sortable(),
                TextColumn::make('scope_id')->placeholder('—'),
                TextColumn::make('value')->formatStateUsing(fn (Setting $record) => json_encode($record->value, JSON_UNESCAPED_SLASHES))->fontFamily('mono'),
                TextColumn::make('description')->limit(60)->toggleable(),
                TextColumn::make('updated_at')->since()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('scope')->options(array_combine(Setting::SCOPES, Setting::SCOPES)),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSettings::route('/'),
        ];
    }
}
