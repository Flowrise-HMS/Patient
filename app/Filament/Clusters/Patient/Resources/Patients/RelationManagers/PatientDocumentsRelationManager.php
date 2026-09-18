<?php

namespace Modules\Patient\Filament\Clusters\Patient\Resources\Patients\RelationManagers;

use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Classes\Services\MediaDocumentService;
use Modules\Core\Filament\RelationManagers\MediaDocumentsRelationManager;
use Modules\Core\Support\OptionalClass;
use Modules\Patient\Enums\DocumentType;
use Modules\Patient\Models\Patient;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Patient-level documents plus every document attached to one of the patient's
 * encounters, so the care team sees the full file in one place.
 */
class PatientDocumentsRelationManager extends MediaDocumentsRelationManager
{
    protected static function documentTypeEnum(): ?string
    {
        return DocumentType::class;
    }

    protected function documentsQuery(): Builder
    {
        /** @var Patient $patient */
        $patient = $this->getOwnerRecord();

        return Media::query()
            ->with('model')
            ->where('collection_name', MediaDocumentService::COLLECTION)
            ->where(function (Builder $query) use ($patient): void {
                $query->where(fn (Builder $own): Builder => $own
                    ->where('model_type', $patient->getMorphClass())
                    ->where('model_id', $patient->getKey()));

                // `encounters` is registered by the Clinical module via resolveRelationUsing;
                // resolving it dynamically keeps this module free of a Clinical import.
                if ($patient->isRelation('encounters')) {
                    $encounters = $patient->encounters();

                    $query->orWhere(fn (Builder $viaEncounter): Builder => $viaEncounter
                        ->where('model_type', $encounters->getRelated()->getMorphClass())
                        ->whereIn('model_id', $encounters->getQuery()->withoutGlobalScope('branch')->select('id')));
                }
            });
    }

    protected function extraColumns(): array
    {
        /** @var Patient $patient */
        $patient = $this->getOwnerRecord();

        return [
            TextColumn::make('source')
                ->label('Attached to')
                ->state(fn (Media $record): string => $this->isPatientOwned($record, $patient)
                    ? 'Patient file'
                    : (string) ($record->model?->encounter_number ?? 'Encounter'))
                ->badge()
                ->color(fn (Media $record): string => $this->isPatientOwned($record, $patient) ? 'primary' : 'info')
                ->url(fn (Media $record): ?string => $this->isPatientOwned($record, $patient)
                    ? null
                    : OptionalClass::when(
                        'Modules\\Clinical\\Filament\\Clusters\\Clinical\\Resources\\Encounters\\EncounterResource',
                        fn (string $resource): string => $resource::getUrl('view', ['record' => $record->model_id]),
                        'Clinical',
                    )),
        ];
    }

    protected function isPatientOwned(Media $media, Patient $patient): bool
    {
        return $media->model_type === $patient->getMorphClass()
            && (string) $media->model_id === (string) $patient->getKey();
    }
}
