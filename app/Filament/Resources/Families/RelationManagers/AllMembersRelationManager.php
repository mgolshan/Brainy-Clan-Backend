<?php

namespace App\Filament\Resources\Families\RelationManagers;

use App\Filament\Support\StaffAccess;
use App\Modules\Core\Exceptions\ApiException;
use App\Modules\Family\Actions\DeleteChildAccountForParent;
use App\Modules\Family\Actions\IssueSignInCode;
use App\Modules\Family\Actions\SendChildDataExport;
use App\Modules\Family\Models\FamilyMember;
use App\Modules\Identity\Actions\RevokeDevice;
use App\Modules\Identity\Models\Device;
use App\Modules\Identity\Models\SocialAccount;
use App\Modules\Identity\Models\User;
use App\Modules\Policy\PolicyResolver;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Members of a family with their email login, linked Google/Facebook/Apple accounts and signed-in devices. Admin staff can issue a
 * one-time sign-in code (same Action as the API), sign a lost device out and — on a verified parent request sent from the
 * owner's own email address — mail a child account's data export to the owner or delete the child account. The `support`
 * role sees the list only (no actions).
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
                    ->authorize(fn (): bool => StaffAccess::isAdmin())
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
                    ->authorize(fn (): bool => StaffAccess::isAdmin())
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
                Action::make('startDataExport')
                    ->label('Start data export')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->requiresConfirmation()
                    ->modalHeading(fn (FamilyMember $record): string => "Email {$record->nickname}'s data to the family owner?")
                    ->modalDescription("Only for a parent request sent from the family owner's own email address. The JSON export (same as “Download my data” in the app) is emailed to the owner's account email; you never see it.")
                    ->visible(fn (FamilyMember $record): bool => self::isParentManagedChild($record))
                    ->authorize(fn (): bool => StaffAccess::isAdmin())
                    ->action(function (FamilyMember $record): void {
                        $staff = StaffAccess::user();

                        if ($staff === null) {
                            return;
                        }

                        try {
                            $sentTo = app(SendChildDataExport::class)->handle($record, $staff);
                        } catch (ApiException $exception) {
                            Notification::make()->title($exception->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()->title("Data export of {$record->nickname} emailed to {$sentTo}")->success()->send();
                    }),
                Action::make('deleteChildAccount')
                    ->label('Delete child account')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->modalHeading(fn (FamilyMember $record): string => "Delete {$record->nickname}'s account?")
                    ->modalDescription("Only for a parent request sent from the family owner's own email address. Removes the member from the family and erases the account (same as removing a child in the app). This cannot be undone. Start a data export first if the parent asked for one.")
                    ->modalSubmitActionLabel('Delete account')
                    ->visible(fn (FamilyMember $record): bool => self::isParentManagedChild($record))
                    ->authorize(fn (): bool => StaffAccess::isAdmin())
                    ->schema(fn (FamilyMember $record): array => [
                        TextInput::make('confirm_nickname')
                            ->label("Type the nickname \"{$record->nickname}\" to confirm")
                            ->required()
                            ->in([$record->nickname])
                            ->validationMessages(['in' => 'The nickname does not match.']),
                    ])
                    ->action(function (FamilyMember $record, array $data): void {
                        $staff = StaffAccess::user();

                        if ($staff === null) {
                            return;
                        }

                        $nickname = $record->nickname;

                        try {
                            app(DeleteChildAccountForParent::class)->handle($record, $staff, (string) ($data['confirm_nickname'] ?? ''));
                        } catch (ApiException $exception) {
                            Notification::make()->title($exception->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()->title("{$nickname}'s account was deleted")->success()->send();
                    }),
            ]);
    }

    /**
     * An active member without their own login (no email, no social account) who is not the owner:
     * the accounts a parent manages and that the staff privacy actions apply to.
     */
    private static function isParentManagedChild(FamilyMember $record): bool
    {
        return $record->status === FamilyMember::STATUS_ACTIVE
            && $record->user_id !== $record->family->owner_user_id
            && ! $record->user->hasOwnLogin();
    }
}
