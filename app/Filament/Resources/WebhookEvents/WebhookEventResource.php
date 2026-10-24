<?php

namespace App\Filament\Resources\WebhookEvents;

use App\Filament\Resources\Families\FamilyResource;
use App\Filament\Resources\WebhookEvents\Pages\ListWebhookEvents;
use App\Filament\Resources\WebhookEvents\Pages\ViewWebhookEvent;
use App\Filament\Support\ReadOnlyForSupport;
use App\Modules\Billing\Models\WebhookEvent;
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
use Illuminate\Support\HtmlString;
use UnitEnum;

/**
 * Received billing webhooks (RevenueCat, Paddle) with their outcome and raw payload, for support. Read-only.
 */
class WebhookEventResource extends Resource
{
    use ReadOnlyForSupport;

    protected static ?string $model = WebhookEvent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static string|UnitEnum|null $navigationGroup = 'Billing';

    protected static ?string $navigationLabel = 'Webhook events';

    protected static ?string $recordTitleAttribute = 'event_id';

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
            TextEntry::make('provider')->badge(),
            TextEntry::make('event_id')->label('Event id')->copyable(),
            TextEntry::make('type')->placeholder('—'),
            TextEntry::make('family.name')->label('Family')->placeholder('—')
                ->url(fn (WebhookEvent $record): ?string => $record->family_id !== null ? FamilyResource::getUrl('view', ['record' => $record->family_id]) : null),
            TextEntry::make('outcome')->badge()->color(fn (?string $state): string => self::outcomeColor($state))->placeholder('—'),
            TextEntry::make('note')->placeholder('—'),
            TextEntry::make('created_at')->label('Received')->dateTime(),
            TextEntry::make('processed_at')->dateTime()->placeholder('—'),
            TextEntry::make('payload')->columnSpanFull()
                ->state(fn (WebhookEvent $record): string => (string) json_encode($record->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
                ->formatStateUsing(fn (string $state): HtmlString => new HtmlString('<pre class="text-xs overflow-x-auto">'.e($state).'</pre>')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('family'))
            ->columns([
                TextColumn::make('created_at')->label('Received')->dateTime()->sortable(),
                TextColumn::make('provider')->badge(),
                TextColumn::make('type')->placeholder('—')->searchable(),
                TextColumn::make('family.name')->label('Family')->placeholder('—'),
                TextColumn::make('outcome')->badge()->color(fn (?string $state): string => self::outcomeColor($state))->placeholder('—'),
                TextColumn::make('note')->placeholder('—')->toggleable(),
                TextColumn::make('event_id')->label('Event id')->searchable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('provider')->options([WebhookEvent::PROVIDER_REVENUECAT => 'RevenueCat', WebhookEvent::PROVIDER_PADDLE => 'Paddle']),
                SelectFilter::make('outcome')->options(array_combine(WebhookEvent::OUTCOMES, WebhookEvent::OUTCOMES)),
            ])
            ->recordActions([ViewAction::make()]);
    }

    /**
     * @return Builder<Model>
     */
    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->with('family');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWebhookEvents::route('/'),
            'view' => ViewWebhookEvent::route('/{record}'),
        ];
    }

    private static function outcomeColor(?string $outcome): string
    {
        return match ($outcome) {
            WebhookEvent::OUTCOME_APPLIED => 'success',
            WebhookEvent::OUTCOME_FAILED => 'danger',
            WebhookEvent::OUTCOME_STALE => 'warning',
            default => 'gray',
        };
    }
}
