<?php

namespace Modules\Patient\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Modules\Core\Filament\Concerns\InteractsWithWidgetShield;
use Modules\Patient\Classes\Services\PatientAnalyticsService;

class PatientsByRegionChartWidget extends ChartWidget
{
    use InteractsWithWidgetShield;

    protected static ?int $sort = 8;

    protected ?string $heading = 'Patients by region';

    protected int|string|array $columnSpan = 1;

    protected function getData(): array
    {
        $series = app(PatientAnalyticsService::class)->getPatientsByRegion(10, $this->resolveBranchId());

        if ($series['labels'] === []) {
            return ['labels' => [], 'datasets' => []];
        }

        return [
            'labels' => $series['labels'],
            'datasets' => [
                [
                    'label' => __('Patients'),
                    'data' => $series['counts'],
                    'backgroundColor' => '#16a34a',
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'responsive' => true,
            'maintainAspectRatio' => false,
            'scales' => [
                'x' => ['beginAtZero' => true],
            ],
        ];
    }

    protected function resolveBranchId(): ?string
    {
        $branchId = Context::get('current_branch_id', Auth::user()?->branch_id);

        return $branchId !== null ? (string) $branchId : null;
    }
}
