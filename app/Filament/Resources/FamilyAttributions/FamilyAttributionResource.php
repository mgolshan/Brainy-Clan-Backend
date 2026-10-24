<?php

namespace App\Filament\Resources\FamilyAttributions;

use App\Filament\Resources\Families\FamilyResource;
use App\Filament\Resources\FamilyAttributions\Pages\ListFamilyAttributions;
use App\Filament\Support\ReadOnlyForSupport;
use App\Filament\Support\StaffAccess;
use App\Modules\Billing\Actions\ChangeFamilyAttribution;
use App\Modules\Billing\Models\AffiliateCode;
use App\Modules\Billing\Models\FamilyAttribution;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Which affiliate code each family came with (first touch, locked at the first paid transaction).
 * Read-only except the staff "Change code" action, which needs a reason and is logged. Family ids
 * are shown to staff only — never in reports for affiliates.
 */
class FamilyAttributionResource extends Resource
{
    use ReadOnlyForSupport;

    protected static ?string $model = FamilyAttribution::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;

    protected static string|UnitEnum|null $navigationGroup = 'Billing';

    protected static ?int $navigationSort = 22;

    protected static ?string $navigationLabel = 'Attributions';

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
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['affiliateCode', 'affiliate', 'changedBy']))
            ->columns([
                TextColumn::make('family_id')->label('Family')->sortable()->searchable()
                    ->url(fn (FamilyAttribution $record): string => FamilyResource::getUrl('view', ['record' => $record->family_id])),
                TextColumn::make('affiliateCode.code')->label('Code')->weight('bold'),
                TextColumn::make('affiliate.name')->label('Affiliate'),
                TextColumn::make('source')->badge(),
                TextColumn::make('attributed_at')->dateTime()->sortable(),
                TextColumn::make('locked_at')->label('Locked (first payment)')->dateTime()->placeholder('—')->sortable(),
                TextColumn::make('changedBy.display_name')->label('Changed by')->placeholder('—')->toggleable(),
                TextColumn::make('change_reason')->label('Reason')->placeholder('—')->wrap()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('affiliate')->relationship('affiliate', 'name'),
                SelectFilter::make('source')->options(array_combine(FamilyAttribution::SOURCES, FamilyAttribution::SOURCES)),
                TernaryFilter::make('locked')->nullable()->attribute('locked_at'),
            ])
            ->recordActions([
                Action::make('changeCode')
                    ->label('Change code')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->visible(fn (): bool => StaffAccess::isAdmin())
                    ->schema([
                        Select::make('affiliate_code_id')->label('New code')
                            ->options(fn (): array => AffiliateCode::query()->orderBy('code')->pluck('code', 'id')->all())
                            ->searchable()->required(),
                        TextInput::make('reason')->required()->maxLength(255)
                            ->helperText('Why (e.g. support ticket, parent’s request). Kept in the activity log.'),
                    ])
                    ->action(function (FamilyAttribution $record, array $data): void {
                        $staff = StaffAccess::user();
                        abort_unless($staff !== null && StaffAccess::isAdmin(), 403);

                        app(ChangeFamilyAttribution::class)->handle(
                            $record,
                            AffiliateCode::query()->findOrFail((int) $data['affiliate_code_id']),
                            (string) $data['reason'],
                            $staff,
                        );

                        Notification::make()->title('Attribution changed')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFamilyAttributions::route('/'),
        ];
    }
}
