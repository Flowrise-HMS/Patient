<?php

namespace Modules\Patient\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Modules\Core\Filament\Concerns\InteractsWithWidgetShield;
use Modules\Patient\Classes\Services\PatientAnalyticsService;

class PatientRegistrationsChartWidget extends ChartWidget
{
    use InteractsWithWidgetShield;

    protected static ?int $sort = 7;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Patient registrations';

    public ?string $filter = 'monthly';

    protected function getFilters(): ?array
    {
        return [
            'monthly' => __('Monthly (12 months)'),
            'yearly' => __('Yearly (5 years)'),
        ];
    }

    protected function getData(): array
    {
        $service = app(PatientAnalyticsService::class);
        $branchId = $this->resolveBranchId();

        $series = $this->filter === 'yearly'
            ? $service->getYearlyRegistrations(5, $branchId)
            : $service->getMonthlyRegistrations(12, $branchId);

        if ($series['labels'] === []) {
            return ['labels' => [], 'datasets' => []];
        }

        return [
            'labels' => $series['labels'],
            'datasets' => [
                [
                    'label' => __('Registrations'),
                    'data' => $series['counts'],
                    'backgroundColor' => '#3b82f6',
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
            'responsive' => true,
            'maintainAspectRatio' => false,
            'scales' => [
                'y' => ['beginAtZero' => true],
            ],
        ];
    }

    protected function resolveBranchId(): ?string
    {
        $branchId = Context::get('current_branch_id', Auth::user()?->branch_id);

        return $branchId !== null ? (string) $branchId : null;
    }
}
