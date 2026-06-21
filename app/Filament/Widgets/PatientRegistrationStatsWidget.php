<?php

namespace Modules\Patient\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Modules\Core\Filament\Concerns\InteractsWithWidgetShield;
use Modules\Patient\Classes\Services\PatientAnalyticsService;

class PatientRegistrationStatsWidget extends BaseWidget
{
    use InteractsWithWidgetShield;

    protected static ?int $sort = 5;

    protected function getStats(): array
    {
        $summary = app(PatientAnalyticsService::class)->getRegistrationSummary(
            $this->resolveBranchId()
        );

        return [
            Stat::make(__('Registered today'), (string) $summary['today'])
                ->description(__('New patients added today'))
                ->descriptionIcon('heroicon-m-user-plus')
                ->color($summary['today'] > 0 ? 'success' : 'gray'),
            Stat::make(__('This week'), (string) $summary['this_week'])
                ->description(__('Registrations since week start'))
                ->descriptionIcon('heroicon-m-calendar-days'),
            Stat::make(__('This month'), (string) $summary['this_month'])
                ->description(__('Registrations since month start'))
                ->descriptionIcon('heroicon-m-chart-bar'),
            Stat::make(__('Active patients'), (string) $summary['total_active'])
                ->description(__('Currently active, not deceased'))
                ->descriptionIcon('heroicon-m-users')
                ->color('primary'),
        ];
    }

    protected function resolveBranchId(): ?string
    {
        $branchId = Context::get('current_branch_id', Auth::user()?->branch_id);

        return $branchId !== null ? (string) $branchId : null;
    }
}
