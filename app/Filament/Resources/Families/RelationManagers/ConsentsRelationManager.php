<?php

namespace App\Filament\Resources\Families\RelationManagers;

use App\Modules\Identity\Models\Consent;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Read-only list of the legal consents given by the family's members (terms, privacy policy and
 * parental consent for children). Consents are append-only: no create/edit/delete here.
 */
class ConsentsRelationManager extends RelationManager
{
    protected static string $relationship = 'consents';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user', 'subjectMember', 'subjectInvite']))
            ->defaultSort('consents.id', 'desc')
            ->columns([
                TextColumn::make('user.display_name')->label('Given by'),
                TextColumn::make('type')->badge()->color(fn (string $state): string => $state === Consent::PARENTAL ? 'warning' : 'gray'),
                TextColumn::make('version'),
                TextColumn::make('subject')->label('For child')->placeholder('—')
                    ->state(fn (Consent $record): ?string => $record->subjectMember->nickname ?? ($record->subjectInvite !== null ? $record->subjectInvite->nickname.' (invite)' : null)),
                TextColumn::make('accepted_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')->options(array_combine(Consent::TYPES, Consent::TYPES)),
            ]);
    }
}
