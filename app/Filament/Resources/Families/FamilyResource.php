<?php

namespace App\Filament\Resources\Families;

use App\Filament\Resources\Families\Pages\EditFamily;
use App\Filament\Resources\Families\Pages\ListFamilies;
use App\Filament\Resources\Families\Pages\ViewFamily;
use App\Filament\Resources\Families\RelationManagers\AllMembersRelationManager;
use App\Filament\Resources\Families\RelationManagers\InvitesRelationManager;
use App\Modules\Family\Models\Family;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Read-mostly support view of families. Children are shown by nickname only.
 */
class FamilyResource extends Resource
{
    protected static ?string $model = Family::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static string|UnitEnum|null $navigationGroup = 'Families';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->required()->maxLength(80),
            TextInput::make('timezone')->required()->rule('timezone:all'),
            TextInput::make('country_code')->required()->length(2),
            Select::make('tier')->options(array_combine(Family::TIERS, Family::TIERS))->required()
                ->helperText('Normally set by Billing; change only for support.'),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            TextEntry::make('name'),
            TextEntry::make('owner.display_name')->label('Owner'),
            TextEntry::make('owner.email')->label('Owner email'),
            TextEntry::make('tier')->badge(),
            TextEntry::make('timezone'),
            TextEntry::make('country_code'),
            TextEntry::make('created_at')->dateTime(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('name')->searchable(),
                TextColumn::make('owner.email')->label('Owner')->searchable(),
                TextColumn::make('tier')->badge()->sortable(),
                TextColumn::make('members_count')->counts('members')->label('Members'),
                TextColumn::make('timezone')->toggleable(),
                TextColumn::make('country_code')->label('Country'),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('tier')->options(array_combine(Family::TIERS, Family::TIERS)),
            ])
            ->recordActions([ViewAction::make(), EditAction::make()]);
    }

    public static function getRelations(): array
    {
        return [
            AllMembersRelationManager::class,
            InvitesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFamilies::route('/'),
            'view' => ViewFamily::route('/{record}'),
            'edit' => EditFamily::route('/{record}/edit'),
        ];
    }
}
