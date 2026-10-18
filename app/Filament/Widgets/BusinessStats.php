<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\AiBatchGenerator;
use App\Filament\Resources\ContentItems\ContentItemResource;
use App\Filament\Resources\Families\FamilyResource;
use App\Filament\Support\DashboardMetrics;
use App\Modules\Content\Models\ContentItem;
use App\Modules\Family\Models\Family;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Subscriptions (paying, trial, grace, family-plan share) and the content bank.
 */
class BusinessStats extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected ?string $pollingInterval = '60s';

    protected ?string $heading = 'Subscriptions and content';

    protected function getStats(): array
    {
        $metrics = app(DashboardMetrics::class);
        $business = $metrics->business();
        $activity = $metrics->activity();

        $familyPlanUrl = FamilyResource::getUrl('index', ['filters' => ['tier' => ['value' => Family::TIER_FAMILY]]]);
        $share = $activity['families'] > 0 ? round(100 * $activity['families_on_family_plan'] / $activity['families']) : 0;
        $locales = collect($business['content_published_by_locale'])
            ->map(fn (int $count, string $locale): string => $locale.' '.number_format($count))
            ->implode(' · ');

        return [
            Stat::make('Paying subscriptions', number_format($business['subscriptions_active']))
                ->icon(Heroicon::OutlinedCreditCard)
                ->description(number_format($business['subscriptions_trial']).' in trial · '.number_format($business['subscriptions_grace']).' in grace period')
                ->descriptionColor($business['subscriptions_grace'] > 0 ? 'warning' : 'gray')
                ->url($familyPlanUrl),
            Stat::make('Family plan', $share.'%')
                ->icon(Heroicon::OutlinedChartPie)
                ->description(number_format($activity['families_on_family_plan']).' of '.number_format($activity['families']).' families')
                ->url($familyPlanUrl),
            Stat::make('Published items', number_format($business['content_published']))
                ->icon(Heroicon::OutlinedBookOpen)
                ->description($locales !== '' ? $locales : 'No published content')
                ->url(ContentItemResource::getUrl('index', ['filters' => ['status' => ['value' => ContentItem::STATUS_PUBLISHED]]])),
            Stat::make('Awaiting review', number_format($business['content_awaiting_review']))
                ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                ->description(number_format($business['batches_awaiting_review']).' AI batches to review')
                ->color($business['content_awaiting_review'] > 0 ? 'warning' : 'gray')
                ->url($business['batches_awaiting_review'] > 0
                    ? AiBatchGenerator::getUrl()
                    : ContentItemResource::getUrl('index', ['filters' => ['status' => ['value' => ContentItem::STATUS_DRAFT]]])),
        ];
    }
}
