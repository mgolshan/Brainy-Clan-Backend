<?php

namespace App\Filament\Support;

/**
 * Chart.js options shared by the dashboard chart widgets.
 */
final class DashboardChartOptions
{
    /**
     * One series: no legend (the heading names it), whole-number y axis from zero.
     *
     * @return array<string, mixed>
     */
    public static function singleSeries(): array
    {
        return [
            'plugins' => [
                'legend' => ['display' => false],
            ],
            'scales' => [
                'y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]],
            ],
        ];
    }
}
