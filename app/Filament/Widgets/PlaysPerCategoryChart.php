<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Support\DashboardChartOptions;
use App\Filament\Support\DashboardMetrics;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

/**
 * Games started in the last 7 UTC days per top-level category.
 */
class PlaysPerCategoryChart extends ChartWidget
{
    protected static ?int $sort = 6;

    protected ?string $pollingInterval = '60s';

    protected ?string $heading = 'Plays per category';

    protected ?string $maxHeight = '240px';

    protected string $color = 'info';

    public function getDescription(): string|Htmlable|null
    {
        return new HtmlString('Games started in the last 7 days, by top-level category. '.Blade::render('<x-filament::link :href="$url" size="sm">Categories</x-filament::link>', ['url' => CategoryResource::getUrl('index')]));
    }

    protected function getData(): array
    {
        $plays = app(DashboardMetrics::class)->playsPerCategory();

        return [
            'datasets' => [
                ['label' => 'Plays', 'data' => array_values($plays), 'borderRadius' => 4],
            ],
            'labels' => array_keys($plays),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return DashboardChartOptions::singleSeries();
    }
}
