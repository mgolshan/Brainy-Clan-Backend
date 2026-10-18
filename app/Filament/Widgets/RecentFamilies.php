<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Families\FamilyResource;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Family\Models\Family;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * The newest families with plan, subscription state and member count (one query plus eager loads).
 */
class RecentFamilies extends TableWidget
{
    public const int LIMIT = 8;

    protected static ?int $sort = 7;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Recent families')
            ->query(fn (): Builder => Family::query()
                ->with('subscription')
                ->withCount('members')
                ->latest('id')
                ->limit(self::LIMIT))
            ->paginated(false)
            ->poll('60s')
            ->columns([
                TextColumn::make('name')->weight('medium'),
                TextColumn::make('tier')->label('Plan')->badge()
                    ->color(fn (string $state): string => $state === Family::TIER_FAMILY ? 'success' : 'gray'),
                TextColumn::make('subscription.status')->label('Subscription')->badge()->default(Subscription::STATUS_NONE)
                    ->color(fn (string $state): string => match ($state) {
                        Subscription::STATUS_ACTIVE => 'success',
                        Subscription::STATUS_GRACE => 'warning',
                        Subscription::STATUS_EXPIRED => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('members_count')->label('Members')->numeric(),
                TextColumn::make('created_at')->label('Joined')->since()->dateTimeTooltip(),
            ])
            ->recordUrl(fn (Family $record): string => FamilyResource::getUrl('view', ['record' => $record]))
            ->headerActions([
                Action::make('allFamilies')->label('All families')->link()->url(FamilyResource::getUrl('index')),
            ])
            ->emptyStateHeading('No families yet');
    }
}
