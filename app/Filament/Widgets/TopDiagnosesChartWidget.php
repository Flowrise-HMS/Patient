<?php

namespace Modules\Patient\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Modules\Core\Filament\Concerns\InteractsWithWidgetShield;
use Modules\Patient\Classes\Services\PatientAnalyticsService;

class TopDiagnosesChartWidget extends ChartWidget
{
    use InteractsWithWidgetShield;

    protected static ?int $sort = 9;

    protected ?string $heading = 'Top diagnoses (90 days)';

    protected int|string|array $columnSpan = 1;

    protected function getData(): array
    {
        $series = app(PatientAnalyticsService::class)->getTopDiagnoses(8, 90, $this->resolveBranchId());

        if ($series['labels'] === []) {
            return ['labels' => [], 'datasets' => []];
        }

        return [
            'labels' => $series['labels'],
            'datasets' => [
                [
                    'data' => $series['counts'],
                    'backgroundColor' => ['#6b7280', '#3b82f6', '#06b6d4', '#16a34a', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899'],
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'responsive' => true,
            'maintainAspectRatio' => false,
        ];
    }

    protected function resolveBranchId(): ?string
    {
        $branchId = Context::get('current_branch_id', Auth::user()?->branch_id);

        return $branchId !== null ? (string) $branchId : null;
    }
}
