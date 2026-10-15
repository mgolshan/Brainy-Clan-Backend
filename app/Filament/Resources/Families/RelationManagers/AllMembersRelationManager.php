<?php

namespace App\Filament\Resources\Families\RelationManagers;

use App\Modules\Family\Actions\IssueSignInCode;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Identity\Actions\RevokeDevice;
use App\Modules\Identity\Models\Device;
use App\Modules\Identity\Models\SocialAccount;
use App\Modules\Identity\Models\User;
use App\Modules\Policy\PolicyResolver;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Members of a family with their email login, linked Google/Facebook/Apple accounts and signed-in devices. Support can issue a
 * one-time sign-in code (same Action as the API) and sign a lost device out.
 */
class AllMembersRelationManager extends RelationManager
{
    protected static string $relationship = 'allMembers';

    protected static ?string $title = 'Members';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nickname')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user.devices', 'user.socialAccounts', 'family']))
            ->columns([
                TextColumn::make('nickname'),
                TextColumn::make('role')->badge(),
                TextColumn::make('age_band')->label('Age band')
                    ->state(fn (FamilyMember $record) => app(PolicyResolver::class)->bandForMember($record)->code ?? '—'),
                TextColumn::make('user.email')->label('Email login')->placeholder('codes only'),
                IconColumn::make('has_password')->label('Password')->boolean()
                    ->state(fn (FamilyMember $record): bool => $record->user->password !== null),
                TextColumn::make('social_accounts')->label('Social sign-in')
                    ->state(fn (FamilyMember $record): array => $record->user->socialAccounts
                        ->map(fn (SocialAccount $account) => ucfirst($account->provider).($account->email !== null ? ' ('.$account->email.')' : ''))
                        ->all())
                    ->listWithLineBreaks()
                    ->placeholder('none'),
                TextColumn::make('devices')->label('Devices')
                    ->state(fn (FamilyMember $record): array => $record->user->devices
                        ->map(fn (Device $device) => $device->name.' ('.$device->platform.', '.($device->last_seen_at?->diffForHumans() ?? 'never').')')
                        ->all())
                    ->listWithLineBreaks()
                    ->placeholder('none'),
                TextColumn::make('difficulty_override')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')->badge(),
                TextColumn::make('joined_at')->dateTime()->toggleable(),
            ])
            ->recordActions([
                Action::make('signInCode')
                    ->label('Sign-in code')
                    ->icon(Heroicon::OutlinedKey)
                    ->requiresConfirmation()
                    ->modalDescription('Issues a one-time code (15 minutes) the member types on their new device. Older unused codes stop working.')
                    ->visible(fn (FamilyMember $record): bool => $record->status === FamilyMember::STATUS_ACTIVE)
                    ->action(function (FamilyMember $record): void {
                        $staff = Auth::user();
                        $issued = app(IssueSignInCode::class)->handle($record, $staff instanceof User ? $staff : null);

                        Notification::make()
                            ->title("Sign-in code for {$record->nickname}: {$issued['code']}")
                            ->body('Valid until '.$issued['sign_in_code']->expires_at->toDayDateTimeString().' (UTC). Shown only once.')
                            ->success()
                            ->persistent()
                            ->send();
                    }),
                Action::make('signOutDevice')
                    ->label('Sign out device')
                    ->icon(Heroicon::OutlinedDevicePhoneMobile)
                    ->color('danger')
                    ->visible(fn (FamilyMember $record): bool => $record->user->devices->isNotEmpty())
                    ->schema(fn (FamilyMember $record): array => [
                        Select::make('device_id')->label('Device')->required()
                            ->options($record->user->devices->mapWithKeys(fn (Device $device) => [$device->id => $device->name.' ('.$device->platform.')'])->all()),
                    ])
                    ->action(function (FamilyMember $record, array $data): void {
                        /** @var Device $device */
                        $device = $record->user->devices()->findOrFail((int) $data['device_id']);
                        app(RevokeDevice::class)->handle($device);

                        Notification::make()->title("Signed out {$device->name}")->success()->send();
                    }),
            ]);
    }
}
