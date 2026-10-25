<?php

namespace App\Filament\Resources\AffiliateCodes;

use App\Filament\Resources\AffiliateCodes\Pages\ManageAffiliateCodes;
use App\Filament\Support\ReadOnlyForSupport;
use App\Modules\Billing\Models\AffiliateCode;
use App\Modules\Core\Support\Brand;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Referral codes (`MAYA`), each of one affiliate, optionally mapped to store offer codes and a
 * Paddle discount. Codes are stored upper case and matched case-insensitively.
 */
class AffiliateCodeResource extends Resource
{
    use ReadOnlyForSupport;

    protected static ?string $model = AffiliateCode::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static string|UnitEnum|null $navigationGroup = 'Billing';

    protected static ?int $navigationSort = 21;

    protected static ?string $navigationLabel = 'Affiliate codes';

    protected static ?string $recordTitleAttribute = 'code';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('code')->required()->maxLength(20)->regex(AffiliateCode::PATTERN)
                ->unique(ignoreRecord: true)
                ->dehydrateStateUsing(fn (?string $state): string => AffiliateCode::normalize((string) $state))
                ->helperText(fn (): string => 'Letters, digits and dashes, up to 20; case-insensitive. Link: '.Brand::domain().'/r/CODE'),
            Select::make('affiliate_id')->relationship('affiliate', 'name')->searchable()->preload()->required(),
            TagsInput::make('store_offer_codes')->label('Store offer codes')->columnSpanFull()
                ->helperText('Apple Offer Codes / Google Play promo codes exactly as RevenueCat reports them in `offer_code`. A store purchase with one of them attributes the family to this code.'),
            TextInput::make('paddle_discount_id')->label('Paddle discount id')->maxLength(100)->placeholder('dsc_…')
                ->helperText('Applied to web checkouts with this code.'),
            Toggle::make('active')->default(true)->inline(false),
            Textarea::make('notes')->rows(2)->maxLength(2000)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('code')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('affiliate'))
            ->columns([
                TextColumn::make('code')->searchable()->sortable()->copyable()->weight('bold'),
                TextColumn::make('affiliate.name')->label('Affiliate')->searchable(),
                IconColumn::make('active')->boolean(),
                TextColumn::make('store_offer_codes')->label('Store offer codes')->badge()->placeholder('—'),
                TextColumn::make('paddle_discount_id')->label('Paddle discount')->placeholder('—')->toggleable(),
                TextColumn::make('attributions_count')->counts('attributions')->label('Families'),
            ])
            ->filters([
                SelectFilter::make('affiliate')->relationship('affiliate', 'name'),
                TernaryFilter::make('active'),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAffiliateCodes::route('/'),
        ];
    }
}
