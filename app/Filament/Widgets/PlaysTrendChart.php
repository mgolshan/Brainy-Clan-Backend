<?php

namespace App\Filament\Widgets;

use App\Filament\Support\DashboardChartOptions;
use App\Filament\Support\DashboardMetrics;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;

/**
 * Games started per UTC day over the last 30 days.
 */
class PlaysTrendChart extends ChartWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '60s';

    protected ?string $heading = 'Plays per day';

    protected ?string $description = 'Games started per UTC day, last 30 days.';

    protected ?string $maxHeight = '260px';

    protected string $color = 'primary';

    protected function getData(): array
    {
        $plays = app(DashboardMetrics::class)->trend()['plays'];

        return [
            'datasets' => [
                ['label' => 'Plays', 'data' => array_values($plays), 'fill' => true, 'tension' => 0.3],
            ],
            'labels' => array_map(fn (string $day): string => CarbonImmutable::parse($day)->format('M j'), array_keys($plays)),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return DashboardChartOptions::singleSeries();
    }
}
