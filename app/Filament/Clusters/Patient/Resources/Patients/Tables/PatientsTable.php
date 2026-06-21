<?php

namespace Modules\Patient\Filament\Clusters\Patient\Resources\Patients\Tables;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Modules\Billing\Services\PatientBalanceQueryService;
use Modules\Clinical\Classes\Actions\PatientActions;
use Modules\Core\Filament\Tables\Columns\CurrencyColumn;
use Modules\Patient\Enums\Gender;
use Modules\Patient\Filament\Clusters\Patient\Resources\Patients\PatientResource;
use Ysfkaya\FilamentPhoneInput\Tables\PhoneColumn;

class PatientsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns(static::getColumns())
            ->filters(static::getFilters())
            ->filters(static::getFilters(), layout: FiltersLayout::Dropdown)
            ->filtersFormColumns(3)
            ->groups(static::getGroupings())
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50, 100])
            ->recordActions(static::getActions())
            ->toolbarActions(static::getBulkActions())
            ->persistFiltersInSession();
    }

    public static function getColumns(): array
    {
        return [
            TextColumn::make('#')->rowIndex(),
            SpatieMediaLibraryImageColumn::make('photo')
                ->imageSize(40)
                ->circular()
                ->tooltip(fn ($record) => $record->full_name),
            TextColumn::make('mrn')
                ->label('MRN')
                ->searchable()
                ->sortable()
                ->weight('bold')
                ->color('primary')
                ->copyable()
                ->copyableState(fn ($state) => $state),
            TextColumn::make('full_name')
                ->label('Patient Name')
                ->searchable(['first_name', 'middle_name', 'last_name'])
                ->sortable()
                ->formatStateUsing(fn ($record) => $record->full_name)
                ->wrap(),
            TextColumn::make('gender')
                ->label('Gender')
                ->badge()
                ->sortable()
                ->formatStateUsing(fn ($state) => $state?->getLabel() ?? '-'),
            TextColumn::make('age')
                ->label('Age')
                ->sortable()
                ->formatStateUsing(fn ($record) => $record->age ? $record->age.' yrs' : '-')
                ->alignCenter(),
            PhoneColumn::make('phone')
                ->label('Phone')
                ->searchable(),
            TextColumn::make('branch.name')
                ->label('Branch')
                ->badge()
                ->sortable()
                ->color('gray'),
            IconColumn::make('is_active')
                ->label('Status')
                ->sortable()
                ->boolean()
                ->trueIcon('heroicon-o-check-circle')
                ->falseIcon('heroicon-o-x-circle')
                ->trueColor('success')
                ->falseColor('danger'),
            CurrencyColumn::make('outstanding_balance')
                ->label('Balance due')
                ->badge()
                ->visible(fn (): bool => class_exists(PatientBalanceQueryService::class) && (Auth::user()?->can('view_patient_balance') ?? false))
                ->color(fn ($record): string => bccomp(
                    app(PatientBalanceQueryService::class)->openBalanceForPatient((string) $record->id) ?? '0',
                    '0',
                    2
                ) > 0 ? 'danger' : 'gray')
                ->getStateUsing(fn ($record): ?string => class_exists(PatientBalanceQueryService::class)
                    ? app(PatientBalanceQueryService::class)->openBalanceForPatient((string) $record->id)
                    : null)
                ->placeholder('—')
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('created_at')
                ->label('Registered')
                ->dateTime('d M Y')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: false),
        ];
    }

    public static function getFilters(): array
    {
        return [
            TrashedFilter::make(),
            SelectFilter::make('branch_id')
                ->label('Branch')
                ->relationship('branch', 'name')
                ->searchable()
                ->preload()
                ->multiple(),
            SelectFilter::make('gender')
                ->label('Gender')
                ->options(Gender::class)
                ->multiple(),
            TernaryFilter::make('is_active')
                ->label('Status')
                ->placeholder('All Patients')
                ->trueLabel('Active Only')
                ->falseLabel('Inactive Only'),
            SelectFilter::make('age_group')
                ->label('Age Group')
                ->options([
                    'neonate' => 'Neonate (0-28 days)',
                    'infant' => 'Infant (0-12 months)',
                    'child' => 'Child (1-12 years)',
                    'adolescent' => 'Adolescent (13-17 years)',
                    'adult' => 'Adult (18-64 years)',
                    'elderly' => 'Elderly (65+ years)',
                ])
                ->query(function ($query, array $data) {
                    return match ($data['value'] ?? null) {
                        'neonate' => $query->whereDate('date_of_birth', '>=', now()->subDays(28)),
                        'infant' => $query->whereBetween('date_of_birth', [now()->subYear(), now()->subDays(28)]),
                        'child' => $query->whereBetween('date_of_birth', [now()->subYears(12), now()->subYear()]),
                        'adolescent' => $query->whereBetween('date_of_birth', [now()->subYears(18), now()->subYears(12)]),
                        'adult' => $query->whereBetween('date_of_birth', [now()->subYears(65), now()->subYears(18)]),
                        'elderly' => $query->whereDate('date_of_birth', '<=', now()->subYears(65)),
                        default => $query,
                    };
                }),
            SelectFilter::make('registration_month')
                ->label('Registration Month')
                ->options(fn () => static::getMonthOptions())
                ->query(function ($query, array $data) {
                    if (! $data['value']) {
                        return $query;
                    }
                    [$year, $month] = explode('-', $data['value']);

                    return $query->whereYear('created_at', $year)->whereMonth('created_at', $month);
                }),
        ];
    }

    public static function getActions(): array
    {
        $actions = app(PatientActions::class);

        return [
            ActionGroup::make([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
                $actions->deactivate(),
                $actions->profileAction(),
                $actions->timelineAction(),
                Action::make('activate')
                    ->label('Activate')
                    ->icon('heroicon-o-user-plus')
                    ->color('success')
                    ->action(fn ($record) => $record->update(['is_active' => true]))
                    ->visible(fn ($record) => ! $record->is_active)
                    ->requiresConfirmation(),
                Action::make('activities')
                    ->icon('heroicon-o-bell-alert')
                    ->url(fn ($record) => PatientResource::getUrl('activities', ['record' => $record])),
            ])
                ->label('Actions')
                ->icon('heroicon-m-ellipsis-horizontal')
                ->color('gray')
                ->button(),
        ];
    }

    public static function getBulkActions(): array
    {
        return [
            ActionGroup::make([
                DeleteBulkAction::make(),
                ForceDeleteBulkAction::make(),
                RestoreBulkAction::make(),
                Action::make('export_selected')
                    ->label('Export Selected')
                    ->icon('heroicon-o-document-arrow-down')
                    ->action(function ($records) {
                        // todo:: Export to CSV/Excel
                    }),
                Action::make('activate_selected')
                    ->label('Activate Selected')
                    ->icon('heroicon-o-user-plus')
                    ->color('success')
                    ->accessSelectedRecords()
                    ->action(function ($records) {
                        $records?->each(fn ($record) => $record?->update(['is_active' => true]));
                    }),
                Action::make('deactivate_selected')
                    ->label('Deactivate Selected')
                    ->icon('heroicon-o-user-minus')
                    ->color('warning')
                    ->accessSelectedRecords()
                    ->action(function ($records) {
                        $records?->each(fn ($record) => $record->update(['is_active' => false]));
                    }),
            ])->label('Bulk Actions'),
        ];
    }

    public static function getGroupings(): array
    {
        return [
            'created_at' => 'Registration Date',
            'branch.name' => 'Branch',
            'gender' => 'Gender',
            'is_active' => 'Status',
        ];
    }

    protected static function getMonthOptions(): array
    {
        $options = [];
        $date = now()->startOfMonth();
        for ($i = 0; $i < 12; $i++) {
            $options[$date->format('Y-m')] = $date->format('F Y');
            $date = $date->subMonth();
        }

        return $options;
    }
}
