<?php

namespace App\Filament\Resources\Families\RelationManagers;

use App\Modules\Family\Models\FamilyMember;
use App\Modules\Policy\PolicyResolver;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Members of a family. Children appear by nickname only (no email, no birth data).
 */
class AllMembersRelationManager extends RelationManager
{
    protected static string $relationship = 'allMembers';

    protected static ?string $title = 'Members';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nickname')
            ->modifyQueryUsing(fn ($query) => $query->with(['user', 'family']))
            ->columns([
                TextColumn::make('nickname'),
                TextColumn::make('role')->badge(),
                TextColumn::make('age_band')->label('Age band')
                    ->state(fn (FamilyMember $record) => app(PolicyResolver::class)->bandForMember($record)->code ?? '—'),
                TextColumn::make('user.email')->label('Email')
                    ->state(fn (FamilyMember $record) => app(PolicyResolver::class)->isChild($record) ? 'child (hidden)' : $record->user->email),
                TextColumn::make('difficulty_override')->placeholder('—'),
                TextColumn::make('status')->badge(),
                TextColumn::make('joined_at')->dateTime(),
            ]);
    }
}
