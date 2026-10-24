<?php

namespace App\Filament\Resources\AccountDeletionRequests;

use App\Filament\Resources\AccountDeletionRequests\Pages\ListAccountDeletionRequests;
use App\Filament\Resources\AccountDeletionRequests\Pages\ViewAccountDeletionRequest;
use App\Filament\Support\ReadOnlyForSupport;
use App\Modules\Family\Models\AccountDeletionRequest;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use UnitEnum;

/**
 * Requests made on the public page `/account/delete` (docs/API.md "Account deletion web page"),
 * for support: who asked (the matched account, email masked), and what happened. Read-only: the
 * page and its emailed link drive every change. No plain email is stored (only an HMAC).
 */
class AccountDeletionRequestResource extends Resource
{
    use ReadOnlyForSupport;

    protected static ?string $model = AccountDeletionRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrash;

    protected static string|UnitEnum|null $navigationGroup = 'Families';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Deletion requests';

    protected static ?string $modelLabel = 'deletion request';

    public const array STATUSES = [
        AccountDeletionRequest::STATUS_PENDING => 'Pending (awaiting email confirmation)',
        AccountDeletionRequest::STATUS_COMPLETED => 'Completed (account deleted)',
        AccountDeletionRequest::STATUS_BLOCKED => 'Blocked (family owner / child account)',
        AccountDeletionRequest::STATUS_SUPERSEDED => 'Superseded (newer request)',
        AccountDeletionRequest::STATUS_NO_ACCOUNT => 'No account with that email',
    ];

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            TextEntry::make('created_at')->label('Requested')->dateTime(),
            TextEntry::make('account')->label('Account')
                ->state(fn (AccountDeletionRequest $record): ?string => self::account($record))
                ->placeholder('No account with that email'),
            TextEntry::make('status')->badge()->color(fn (string $state): string => self::statusColor($state)),
            TextEntry::make('expires_at')->label('Link expires')->dateTime(),
            TextEntry::make('confirmed_at')->label('Confirmed at')->dateTime()->placeholder('—'),
            TextEntry::make('completed_at')->label('Completed at')
                ->state(fn (AccountDeletionRequest $record): ?Carbon => self::completedAt($record))
                ->dateTime()->placeholder('—'),
            TextEntry::make('updated_at')->label('Last change')->dateTime(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user'))
            ->columns([
                TextColumn::make('created_at')->label('Requested')->dateTime()->sortable(),
                TextColumn::make('account')->label('Account')
                    ->state(fn (AccountDeletionRequest $record): ?string => self::account($record))
                    ->placeholder('No account'),
                TextColumn::make('status')->badge()->color(fn (string $state): string => self::statusColor($state)),
                TextColumn::make('confirmed_at')->label('Confirmed at')->dateTime()->placeholder('—')->sortable(),
                TextColumn::make('completed_at')->label('Completed at')
                    ->state(fn (AccountDeletionRequest $record): ?Carbon => self::completedAt($record))
                    ->dateTime()->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')->options(self::STATUSES),
            ])
            ->recordActions([ViewAction::make()]);
    }

    /**
     * @return Builder<Model>
     */
    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->with('user');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAccountDeletionRequests::route('/'),
            'view' => ViewAccountDeletionRequest::route('/{record}'),
        ];
    }

    /**
     * "#12 · Mom · m***@example.com" — the matched account, its email masked (erased accounts
     * have none left).
     */
    public static function account(AccountDeletionRequest $record): ?string
    {
        $user = $record->user;

        if ($user === null) {
            return $record->user_id !== null ? "#{$record->user_id}" : null;
        }

        $email = self::maskEmail($user->email);

        return "#{$user->id} · {$user->display_name}".($email !== null ? " · {$email}" : '');
    }

    public static function maskEmail(?string $email): ?string
    {
        if ($email === null || ! str_contains($email, '@')) {
            return null;
        }

        [$local, $domain] = explode('@', $email, 2);

        return mb_substr($local, 0, 1).'***@'.$domain;
    }

    /**
     * The account was deleted when the emailed link was confirmed (only `completed` requests).
     */
    private static function completedAt(AccountDeletionRequest $record): ?Carbon
    {
        return $record->status === AccountDeletionRequest::STATUS_COMPLETED ? $record->confirmed_at : null;
    }

    private static function statusColor(string $status): string
    {
        return match ($status) {
            AccountDeletionRequest::STATUS_PENDING => 'warning',
            AccountDeletionRequest::STATUS_COMPLETED => 'success',
            AccountDeletionRequest::STATUS_BLOCKED => 'danger',
            default => 'gray',
        };
    }
}
