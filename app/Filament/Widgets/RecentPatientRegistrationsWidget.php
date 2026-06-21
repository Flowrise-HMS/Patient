<?php

namespace Modules\Patient\Filament\Widgets;

use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Widgets\TableWidget as BaseTableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Modules\Core\Filament\Concerns\InteractsWithWidgetShield;
use Modules\Patient\Filament\Clusters\Patient\Resources\Patients\PatientResource;
use Modules\Patient\Models\Patient;

class RecentPatientRegistrationsWidget extends BaseTableWidget
{
    use InteractsWithWidgetShield;

    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Recent registrations';

    protected function getTableQuery(): Builder
    {
        return Patient::query()
            ->when($this->resolveBranchId(), fn (Builder $query, string $branchId) => $query->where('branch_id', $branchId))
            ->where('created_at', '>=', now()->subDays(7))
            ->latest();
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('mrn')
                ->label(__('MRN'))
                ->placeholder('—'),
            TextColumn::make('full_name')
                ->label(__('Patient'))
                ->searchable(['first_name', 'last_name', 'mrn']),
            TextColumn::make('gender')
                ->label(__('Gender'))
                ->badge(),
            TextColumn::make('region')
                ->label(__('Region'))
                ->getStateUsing(fn (Patient $record): string => $this->resolveRegionLabel($record)),
            TextColumn::make('created_at')
                ->label(__('Registered'))
                ->since()
                ->sortable(),
        ];
    }

    protected function getTableActions(): array
    {
        return [
            Action::make('view')
                ->label(__('View'))
                ->url(fn (Patient $record): string => PatientResource::getUrl('view', ['record' => $record]))
                ->icon('heroicon-m-eye'),
        ];
    }

    protected function isTablePaginationEnabled(): bool
    {
        return false;
    }

    protected function resolveBranchId(): ?string
    {
        $branchId = Context::get('current_branch_id', Auth::user()?->branch_id);

        return $branchId !== null ? (string) $branchId : null;
    }

    protected function resolveRegionLabel(Patient $record): string
    {
        $address = $record->address ?? [];

        foreach (['region', 'district', 'city'] as $key) {
            $value = $address[$key] ?? null;
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return '—';
    }
}
