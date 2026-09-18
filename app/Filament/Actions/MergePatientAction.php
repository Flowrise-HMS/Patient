<?php

namespace Modules\Patient\Filament\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Modules\Patient\Classes\Services\PatientMergeService;
use Modules\Patient\Classes\Services\PatientSearchService;
use Modules\Patient\Exceptions\PatientMergeException;
use Modules\Patient\Filament\Clusters\Patient\Resources\Patients\PatientResource;
use Modules\Patient\Models\Patient;

/**
 * Header action on ViewPatient: the record being viewed is the duplicate; the
 * user picks the profile that survives.
 */
class MergePatientAction
{
    public static function make(): Action
    {
        return Action::make('merge')
            ->label(__('Merge into another patient'))
            ->icon(Heroicon::OutlinedArrowsPointingIn)
            ->color('danger')
            ->modalHeading(__('Merge duplicate patient profile'))
            ->modalDescription(__('This profile is the duplicate. Its encounters, bills, documents and other records move to the surviving patient you select, and this profile is archived.'))
            ->modalWidth(Width::FourExtraLarge)
            ->modalSubmitActionLabel(__('Merge patients'))
            ->requiresConfirmation()
            ->visible(fn (Patient $record): bool => static::allowedFor($record))
            ->authorize(fn (Patient $record): bool => static::allowedFor($record))
            ->schema([
                Select::make('target_patient_id')
                    ->label(__('Surviving patient (its details win)'))
                    ->required()
                    ->searchable()
                    ->live()
                    ->searchPrompt(__('Search by MRN, old hospital number, name or phone'))
                    ->getSearchResultsUsing(fn (string $search, Patient $record): array => app(PatientSearchService::class)
                        ->searchForMergeTarget($search, $record)
                        ->mapWithKeys(fn (Patient $patient): array => [$patient->getKey() => static::optionLabel($patient)])
                        ->all())
                    ->getOptionLabelUsing(fn ($value): ?string => ($patient = static::findTarget($value)) ? static::optionLabel($patient) : null),
                Textarea::make('reason')
                    ->label(__('Reason'))
                    ->rows(2)
                    ->maxLength(500)
                    ->placeholder(__('e.g. Registered twice at the front desk on 12 May')),
                View::make('patient::filament.actions.merge-preview')
                    ->visible(fn (Get $get): bool => filled($get('target_patient_id')))
                    ->viewData(function (Get $get, Patient $record): array {
                        $target = static::findTarget($get('target_patient_id'));

                        if ($target === null) {
                            return ['preview' => null, 'source' => $record, 'target' => null];
                        }

                        return [
                            'preview' => app(PatientMergeService::class)->preview($record, $target),
                            'source' => $record,
                            'target' => $target,
                        ];
                    }),
            ])
            ->action(function (array $data, Patient $record, Action $action) {
                $target = static::findTarget($data['target_patient_id'] ?? null);

                if ($target === null) {
                    Notification::make()->danger()->title(__('Surviving patient not found'))->send();
                    $action->halt();
                }

                try {
                    app(PatientMergeService::class)->merge($record, $target, Auth::user(), $data['reason'] ?? null);
                } catch (PatientMergeException $exception) {
                    Notification::make()->danger()->title(__('Merge blocked'))->body($exception->getMessage())->send();
                    $action->halt();
                }

                Notification::make()
                    ->success()
                    ->title(__('Patients merged'))
                    ->body(__('MRN :source was merged into MRN :target.', ['source' => $record->mrn, 'target' => $target->mrn]))
                    ->send();

                return redirect(PatientResource::getUrl('view', ['record' => $target]));
            });
    }

    protected static function allowedFor(Patient $record): bool
    {
        return ! $record->isMerged()
            && ! $record->trashed()
            && (Auth::user()?->can('merge', $record) ?? false);
    }

    protected static function findTarget(mixed $id): ?Patient
    {
        if (blank($id)) {
            return null;
        }

        return Patient::query()->withoutGlobalScopes()->whereNull('deleted_at')->find($id);
    }

    protected static function optionLabel(Patient $patient): string
    {
        $parts = array_filter([
            $patient->mrn,
            $patient->old_hospital_number ? __('old :number', ['number' => $patient->old_hospital_number]) : null,
        ]);

        return $patient->full_name.' ('.implode(', ', $parts).')';
    }
}
