<?php

namespace App\Filament\Resources\Families;

use App\Filament\Resources\Families\Pages\EditFamily;
use App\Filament\Resources\Families\Pages\ListFamilies;
use App\Filament\Resources\Families\Pages\ViewFamily;
use App\Filament\Resources\Families\RelationManagers\AllMembersRelationManager;
use App\Filament\Resources\Families\RelationManagers\ConsentsRelationManager;
use App\Filament\Resources\Families\RelationManagers\InvitesRelationManager;
use App\Filament\Support\ReadOnlyForSupport;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Family\Models\Family;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Read-mostly support view of families. Children are shown by nickname only.
 */
class FamilyResource extends Resource
{
    use ReadOnlyForSupport;

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
            Section::make('Subscription')
                ->description('Read-only: the most recently updated plan, set by RevenueCat (store) or Paddle (web) webhooks, the app sync and the hourly reconciliation. Webhooks are under Billing → Webhook events.')
                ->columnSpanFull()->columns(4)
                ->schema([
                    TextEntry::make('subscription.status')->label('Status')->badge()->default(Subscription::STATUS_NONE)
                        ->color(fn (string $state): string => match ($state) {
                            Subscription::STATUS_ACTIVE => 'success',
                            Subscription::STATUS_GRACE => 'warning',
                            Subscription::STATUS_EXPIRED => 'danger',
                            default => 'gray',
                        }),
                    TextEntry::make('subscription.store')->label('Store')->placeholder('—'),
                    TextEntry::make('subscription.product_id')->label('Product')->placeholder('—'),
                    TextEntry::make('subscription.environment')->label('Environment')->placeholder('—'),
                    TextEntry::make('subscription.expires_at')->label('Expires')->dateTime()->placeholder('—'),
                    TextEntry::make('subscription.grace_expires_at')->label('Grace period ends')->dateTime()->placeholder('—'),
                    IconEntry::make('subscription.will_renew')->label('Will renew')->boolean(),
                    TextEntry::make('subscription.last_event_type')->label('Last event')->placeholder('—'),
                    TextEntry::make('revenuecat_app_user_id')->label('RevenueCat app user id')
                        ->state(fn (Family $record): string => 'family-'.$record->id)->copyable(),
                    TextEntry::make('subscription.updated_at')->label('Updated')->dateTime()->placeholder('—'),
                    TextEntry::make('subscription.provider')->label('Provider')->placeholder('—'),
                    TextEntry::make('subscription.external_id')->label('Paddle subscription')->placeholder('—')->copyable(),
                    TextEntry::make('first_paid_at')->label('First paid')->dateTime()->placeholder('—'),
                    TextEntry::make('attribution.affiliateCode.code')->label('Referral code')->placeholder('—')
                        ->helperText(fn (Family $record): ?string => $record->attribution !== null ? 'Source: '.$record->attribution->source : null),
                ]),
        ]);
    }

    /**
     * View/edit pages load the owner and the subscription with the record.
     *
     * @return Builder<Model>
     */
    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->with(['owner', 'subscription', 'attribution.affiliateCode']);
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
            ConsentsRelationManager::class,
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
