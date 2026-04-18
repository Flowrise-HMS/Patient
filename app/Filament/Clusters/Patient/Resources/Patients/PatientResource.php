<?php

namespace Modules\Patient\Filament\Clusters\Patient\Resources\Patients;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Modules\Clinical\Filament\Clusters\Workspace\Pages\PatientProfile;
use Modules\Clinical\Filament\Clusters\Workspace\Pages\Timeline;
use Modules\Core\Enums\NavigationGroup;
use Modules\Patient\Classes\Services\PatientSearchService;
use Modules\Patient\Filament\Clusters\Patient\PatientCluster;
use Modules\Patient\Filament\Clusters\Patient\Resources\Patients\Pages\CreatePatient;
use Modules\Patient\Filament\Clusters\Patient\Resources\Patients\Pages\EditPatient;
use Modules\Patient\Filament\Clusters\Patient\Resources\Patients\Pages\ListPatientActivities;
use Modules\Patient\Filament\Clusters\Patient\Resources\Patients\Pages\ListPatients;
use Modules\Patient\Filament\Clusters\Patient\Resources\Patients\Pages\ViewPatient;
use Modules\Patient\Filament\Clusters\Patient\Resources\Patients\RelationManagers\AllergiesRelationManager;
use Modules\Patient\Filament\Clusters\Patient\Resources\Patients\Schemas\PatientForm;
use Modules\Patient\Filament\Clusters\Patient\Resources\Patients\Schemas\PatientInfolist;
use Modules\Patient\Filament\Clusters\Patient\Resources\Patients\Tables\PatientsTable;
use Modules\Patient\Models\Patient;

class PatientResource extends Resource
{
    protected static ?string $model = Patient::class;

    // protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::CLINICAL;

    protected static ?string $cluster = PatientCluster::class;

    protected static ?string $recordTitleAttribute = 'mrn';

    public static function getGloballySearchableAttributes(): array
    {
        return app(PatientSearchService::class)->getSearchableFields();
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Name' => $record->full_name,
            'MRN' => $record->mrn,
            'Phone' => $record->phone,
            'Email' => $record->email,
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return PatientForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PatientInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PatientsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            AllergiesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPatients::route('/'),
            'create' => CreatePatient::route('/create'),
            'view' => ViewPatient::route('/{record}'),
            'edit' => EditPatient::route('/{record}/edit'),
            'activities' => ListPatientActivities::route('/{record}/activities'),
        ];
    }

    public static function timelineAction()
    {
        return Action::make('view_timeline')
            ->label('Timeline')
            ->icon('heroicon-m-clock')
            ->color('gray')
            ->url(fn (Patient $record) => Timeline::getUrl(['patient' => $record->id]), shouldOpenInNewTab: true);
    }

    public static function profileAction()
    {
        return Action::make('view_profile')
            ->label('View Full Profile')
            ->icon('heroicon-m-user-circle')
            ->url(fn ($record) => PatientProfile::getUrl(['patient' => $record?->id]), shouldOpenInNewTab: true)
            ->color('gray');
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
