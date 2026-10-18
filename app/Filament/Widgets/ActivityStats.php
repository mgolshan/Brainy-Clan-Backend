<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Families\FamilyResource;
use App\Filament\Support\DashboardMetrics;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Families, players, plays and points (today and the last 7 UTC days).
 */
class ActivityStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '60s';

    protected ?string $heading = 'Activity';

    protected ?string $description = 'Days are UTC calendar days; figures may be up to a minute old.';

    protected function getStats(): array
    {
        $metrics = app(DashboardMetrics::class)->activity();

        return [
            Stat::make('Families', number_format($metrics['families']))
                ->icon(Heroicon::OutlinedHome)
                ->description('+'.number_format($metrics['families_new_week']).' new in 7 days')
                ->color($metrics['families_new_week'] > 0 ? 'success' : 'gray')
                ->url(FamilyResource::getUrl('index')),
            Stat::make('Members', number_format($metrics['members']))
                ->icon(Heroicon::OutlinedUsers)
                ->description(number_format($metrics['players_today']).' played today · '.number_format($metrics['players_week']).' in 7 days')
                ->url(FamilyResource::getUrl('index')),
            Stat::make('Plays today', number_format($metrics['plays_today']))
                ->icon(Heroicon::OutlinedPlay)
                ->description(number_format($metrics['plays_week']).' in 7 days')
                ->chart(array_map(floatval(...), array_values($metrics['plays_daily'])))
                ->color('primary'),
            Stat::make('Points today', number_format($metrics['points_today']))
                ->icon(Heroicon::OutlinedStar)
                ->description(number_format($metrics['points_week']).' awarded in 7 days')
                ->chart(array_map(floatval(...), array_values($metrics['points_daily'])))
                ->color('warning'),
        ];
    }
}
