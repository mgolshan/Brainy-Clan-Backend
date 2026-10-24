<?php

namespace App\Filament\Resources\Affiliates;

use App\Filament\Resources\Affiliates\Pages\ManageAffiliates;
use App\Filament\Support\ReadOnlyForSupport;
use App\Modules\Billing\Models\Affiliate;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Affiliate program (B10): influencers / partners, their commission terms and payout notes.
 * Staff-only; influencers never see family data. Changes are in the activity log.
 */
class AffiliateResource extends Resource
{
    use ReadOnlyForSupport;

    protected static ?string $model = Affiliate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Billing';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->required()->maxLength(120),
            TextInput::make('contact_email')->email()->maxLength(255),
            TextInput::make('commission_rate')->label('Commission rate (0–1)')->numeric()->required()
                ->minValue(0)->maxValue(1)->step(0.01)->default((string) config('billing.affiliates.commission_rate'))
                ->helperText('Share of net revenue (after store/Paddle fee and tax). 0.25 = 25%.'),
            TextInput::make('commission_months')->label('Commission months')->integer()->required()
                ->minValue(0)->maxValue(120)->default((int) config('billing.affiliates.commission_months'))
                ->helperText('Counted from the family’s first paid transaction.'),
            Select::make('status')->options(array_combine(Affiliate::STATUSES, Affiliate::STATUSES))->required()->default(Affiliate::STATUS_ACTIVE)
                ->helperText('paused: codes stop attributing new families; ended: also no new commission.'),
            Textarea::make('contact_note')->rows(2)->maxLength(2000),
            Textarea::make('payout_method_note')->label('Payout method')->rows(2)->maxLength(2000)->columnSpanFull()
                ->helperText('How we pay (e.g. Interac e-Transfer to …, PayPal …). Never store full card or bank numbers.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('status')->badge()->color(fn (string $state): string => match ($state) {
                    Affiliate::STATUS_ACTIVE => 'success',
                    Affiliate::STATUS_PAUSED => 'warning',
                    default => 'gray',
                }),
                TextColumn::make('commission_rate')->label('Rate')->formatStateUsing(fn (string $state): string => rtrim(rtrim(number_format((float) $state * 100, 2), '0'), '.').'%'),
                TextColumn::make('commission_months')->label('Months'),
                TextColumn::make('codes_count')->counts('codes')->label('Codes'),
                TextColumn::make('attributions_count')->counts('attributions')->label('Families'),
                TextColumn::make('contact_email')->toggleable(),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(array_combine(Affiliate::STATUSES, Affiliate::STATUSES)),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAffiliates::route('/'),
        ];
    }
}
