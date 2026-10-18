<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\WebhookEvents\WebhookEventResource;
use App\Filament\Support\DashboardMetrics;
use App\Modules\Billing\Models\WebhookEvent;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * The latest failed webhook events; shown only when there was one in the last 7 days.
 */
class RecentWebhookErrors extends TableWidget
{
    public const int LIMIT = 5;

    protected static ?int $sort = 9;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return app(DashboardMetrics::class)->health()['webhook_errors_week'] > 0;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Recent webhook errors')
            ->query(fn (): Builder => WebhookEvent::query()
                ->with('family')
                ->where('outcome', WebhookEvent::OUTCOME_FAILED)
                ->latest('id')
                ->limit(self::LIMIT))
            ->paginated(false)
            ->columns([
                TextColumn::make('created_at')->label('Received')->since()->dateTimeTooltip(),
                TextColumn::make('provider')->badge(),
                TextColumn::make('type')->placeholder('—'),
                TextColumn::make('family.name')->label('Family')->placeholder('—'),
                TextColumn::make('note')->wrap()->placeholder('—'),
            ])
            ->recordUrl(fn (WebhookEvent $record): string => WebhookEventResource::getUrl('view', ['record' => $record]))
            ->headerActions([
                Action::make('allErrors')->label('All failed events')->link()
                    ->url(WebhookEventResource::getUrl('index', ['filters' => ['outcome' => ['value' => WebhookEvent::OUTCOME_FAILED]]])),
            ]);
    }
}
