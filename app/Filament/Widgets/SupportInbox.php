<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\SupportMessages\SupportMessageResource;
use App\Modules\Support\Actions\SupportInboxStats;
use App\Modules\Support\Models\SupportMessage;
use Carbon\CarbonInterface;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * "Support messages" card (docs/PLAN.md "Support channel without a public mailto"): new (unread)
 * messages in warning colour, plus the unanswered count and the age of the oldest unanswered one;
 * links to the inbox filtered to new. One aggregate query, not cached, so it is exact as soon as a
 * message arrives or its status changes.
 */
class SupportInbox extends StatsOverviewWidget
{
    protected static ?int $sort = 0;

    protected ?string $pollingInterval = '60s';

    protected ?string $heading = 'Support';

    protected function getColumns(): int|array|null
    {
        return 1;
    }

    protected function getStats(): array
    {
        $stats = app(SupportInboxStats::class)->handle();
        $oldest = $stats['oldest_unanswered_at'];

        return [
            Stat::make('Support messages', number_format($stats['new']).' new')
                ->icon(Heroicon::OutlinedLifebuoy)
                ->description(number_format($stats['unanswered']).' unanswered'.($oldest !== null ? ' · oldest '.$oldest->diffForHumans(syntax: CarbonInterface::DIFF_ABSOLUTE).' old' : ''))
                ->descriptionColor($stats['unanswered'] > 0 ? 'warning' : 'gray')
                ->color($stats['new'] > 0 ? 'warning' : 'success')
                ->url(SupportMessageResource::getUrl('index', ['filters' => ['status' => ['value' => SupportMessage::STATUS_NEW]]])),
        ];
    }
}
