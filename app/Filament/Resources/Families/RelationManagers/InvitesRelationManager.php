<?php

namespace App\Filament\Resources\Families\RelationManagers;

use App\Filament\Support\StaffAccess;
use App\Modules\Family\Actions\ResetInvite;
use App\Modules\Family\Actions\RevokeInvite;
use App\Modules\Family\Models\Invite;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InvitesRelationManager extends RelationManager
{
    protected static string $relationship = 'invites';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('code')
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('code')->fontFamily('mono')->copyable(),
                TextColumn::make('nickname'),
                TextColumn::make('expires_at')->dateTime(),
                TextColumn::make('used_at')->dateTime()->placeholder('unused'),
                TextColumn::make('status')->badge()->state(fn (Invite $record) => match (true) {
                    $record->used_at !== null => 'used',
                    $record->expires_at->isPast() => 'expired',
                    default => 'pending',
                }),
            ])
            ->recordActions([
                Action::make('reset')
                    ->label('Reset code')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->requiresConfirmation()
                    ->visible(fn (Invite $record) => $record->used_at === null)
                    ->authorize(fn (): bool => StaffAccess::isAdmin())
                    ->action(function (Invite $record): void {
                        $invite = app(ResetInvite::class)->handle($record);
                        Notification::make()->title("New code: {$invite->code}")->success()->send();
                    }),
                Action::make('revoke')
                    ->label('Revoke')
                    ->color('danger')
                    ->icon(Heroicon::OutlinedTrash)
                    ->requiresConfirmation()
                    ->visible(fn (Invite $record) => $record->used_at === null)
                    ->authorize(fn (): bool => StaffAccess::isAdmin())
                    ->action(fn (Invite $record) => app(RevokeInvite::class)->handle($record)),
            ]);
    }
}
