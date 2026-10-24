<?php

namespace App\Filament\Resources\CommissionEntries;

use App\Filament\Resources\CommissionEntries\Pages\ListCommissionEntries;
use App\Filament\Support\ReadOnlyForSupport;
use App\Modules\Billing\Models\CommissionEntry;
use App\Modules\Billing\Support\Money;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * The affiliate commission ledger, read-only (append-only: refunds are negative entries). Status
 * moves pending → payable (daily, after the refund window) → paid (Monthly payouts).
 */
class CommissionEntryResource extends Resource
{
    use ReadOnlyForSupport;

    protected static ?string $model = CommissionEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Billing';

    protected static ?int $navigationSort = 23;

    protected static ?string $navigationLabel = 'Commission entries';

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

    public static function table(Table $table): Table
    {
        $money = fn (string $column) => TextColumn::make($column)
            ->formatStateUsing(fn (?int $state, CommissionEntry $record): string => $state === null ? '—' : Money::format($state, $record->currency))
            ->alignEnd();

        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['affiliate', 'affiliateCode']))
            ->columns([
                TextColumn::make('occurred_at')->label('Date')->dateTime()->sortable(),
                TextColumn::make('affiliate.name')->label('Affiliate')->searchable(),
                TextColumn::make('affiliateCode.code')->label('Code')->placeholder('—'),
                TextColumn::make('family_id')->label('Family')->placeholder('—')->toggleable(),
                TextColumn::make('source')->badge(),
                TextColumn::make('kind')->badge()->color(fn (string $state): string => $state === CommissionEntry::KIND_SALE ? 'success' : 'danger'),
                TextColumn::make('currency'),
                $money('gross_minor')->label('Gross'),
                $money('tax_minor')->label('Tax')->toggleable(isToggledHiddenByDefault: true),
                $money('fee_minor')->label('Fee')->toggleable(isToggledHiddenByDefault: true),
                $money('net_minor')->label('Net'),
                TextColumn::make('commission_rate')->label('Rate')->formatStateUsing(fn (string $state): string => rtrim(rtrim(number_format((float) $state * 100, 2), '0'), '.').'%'),
                $money('commission_minor')->label('Commission')->weight('bold'),
                TextColumn::make('commission_usd_minor')->label('Commission USD')->alignEnd()->toggleable()
                    ->formatStateUsing(fn (?int $state): string => $state === null ? '—' : Money::format($state, 'USD'))->placeholder('—'),
                TextColumn::make('status')->badge()->color(fn (string $state): string => match ($state) {
                    CommissionEntry::STATUS_PAYABLE => 'warning',
                    CommissionEntry::STATUS_PAID => 'success',
                    default => 'gray',
                }),
                TextColumn::make('payable_at')->dateTime()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('paid_at')->dateTime()->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('source_reference')->label('Transaction')->toggleable(isToggledHiddenByDefault: true)->searchable(),
            ])
            ->filters([
                SelectFilter::make('affiliate')->relationship('affiliate', 'name'),
                SelectFilter::make('status')->options(array_combine(CommissionEntry::STATUSES, CommissionEntry::STATUSES)),
                SelectFilter::make('source')->options([CommissionEntry::SOURCE_REVENUECAT => 'RevenueCat', CommissionEntry::SOURCE_PADDLE => 'Paddle']),
                SelectFilter::make('kind')->options(array_combine(CommissionEntry::KINDS, CommissionEntry::KINDS)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCommissionEntries::route('/'),
        ];
    }
}
