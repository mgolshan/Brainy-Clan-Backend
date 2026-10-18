<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Families\FamilyResource;
use App\Filament\Support\DashboardChartOptions;
use App\Filament\Support\DashboardMetrics;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

/**
 * Families created per UTC day over the last 30 days.
 */
class NewFamiliesChart extends ChartWidget
{
    protected static ?int $sort = 5;

    protected ?string $pollingInterval = '60s';

    protected ?string $heading = 'New families per day';

    protected ?string $maxHeight = '240px';

    protected string $color = 'success';

    public function getDescription(): string|Htmlable|null
    {
        return new HtmlString('Sign-ups per UTC day, last 30 days. '.Blade::render('<x-filament::link :href="$url" size="sm">All families</x-filament::link>', ['url' => FamilyResource::getUrl('index')]));
    }

    protected function getData(): array
    {
        $families = app(DashboardMetrics::class)->trend()['families'];

        return [
            'datasets' => [
                ['label' => 'New families', 'data' => array_values($families), 'fill' => true, 'tension' => 0.3],
            ],
            'labels' => array_map(fn (string $day): string => CarbonImmutable::parse($day)->format('M j'), array_keys($families)),
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
