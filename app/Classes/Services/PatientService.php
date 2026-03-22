<?php

namespace Modules\Patient\Classes\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Patient\Events\PatientDeactivated;
use Modules\Patient\Events\PatientRegistered;
use Modules\Patient\Events\PatientUpdated;
use Modules\Patient\Models\Patient;
use Modules\Patient\Models\PatientSchool;

class PatientService
{
    public function __construct(
        protected PatientIdentifierService $identifierService,
        protected EmergencyContactService $emergencyContactService,
        protected PatientSearchService $searchService,
        protected PatientSchoolService $schoolService
    ) {}

    public function all(): Collection
    {
        return Patient::all();
    }

    public function getActive(): Collection
    {
        return Patient::active()->get();
    }

    public function getTrashed(): Collection
    {
        return Patient::onlyTrashed()->get();
    }

    public function find(string $id): ?Patient
    {
        return Patient::find($id);
    }

    public function findByMrn(string $mrn): ?Patient
    {
        return Patient::where('mrn', $mrn)->first();
    }

    public function findByGlobalUuid(string $uuid): ?Patient
    {
        return Patient::where('global_uuid', $uuid)->first();
    }

    public function findOrFail(string $id): Patient
    {
        return Patient::findOrFail($id);
    }

    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        $query = Patient::query();

        $this->applyFilters($query, $filters);

        return $query->paginate($perPage);
    }

    public function search(string $term, int $limit = 10): Collection
    {
        return $this->searchService->search($term, $limit);
    }

    public function create(array $data): Patient
    {
        return DB::transaction(function () use ($data) {
            $data['global_uuid'] = $data['global_uuid'] ?? generate_global_uuid();

            $data['created_by'] = auth()->id();

            $patient = Patient::create($data);

            if (! empty($data['emergency_contact'])) {
                $this->emergencyContactService->create(
                    $patient,
                    $data['emergency_contact']
                );
            }

            if (! empty($data['identifiers']) && is_array($data['identifiers'])) {
                foreach ($data['identifiers'] as $identifier) {
                    if (! empty($identifier['type']) && ! empty($identifier['value'])) {
                        $this->identifierService->create($patient, $identifier);
                    }
                }
            }

            if (! empty($data['school'])) {
                $this->addSchool($patient, array_merge($data['school'], ['is_current' => true]));
            }

            event(new PatientRegistered($patient));

            Log::info('Patient registered', [
                'patient_id' => $patient->id,
                'mrn' => $patient->mrn,
                'branch_id' => $patient->branch_id,
                'registered_by' => auth()->id(),
            ]);

            return $patient;
        });
    }

    public function update(Patient $patient, array $data): Patient
    {
        return DB::transaction(function () use ($patient, $data) {
            $data['updated_by'] = auth()->id();

            $patient->update($data);

            if (isset($data['emergency_contact'])) {
                $this->emergencyContactService->updatePrimaryContact($patient, $data['emergency_contact']);
            }

            event(new PatientUpdated($patient));

            Log::info('Patient updated', [
                'patient_id' => $patient->id,
                'mrn' => $patient->mrn,
                'updated_by' => auth()->id(),
            ]);

            return $patient->fresh();
        });
    }

    public function delete(Patient $patient, bool $force = false): bool
    {
        return DB::transaction(function () use ($patient, $force) {
            if ($force) {
                return $patient->forceDelete();
            }

            return $patient->delete();
        });
    }

    public function restore(string $id): Patient
    {
        $patient = Patient::withTrashed()->findOrFail($id);
        $patient->restore();

        Log::info('Patient restored', [
            'patient_id' => $patient->id,
            'mrn' => $patient->mrn,
            'restored_by' => auth()->id(),
        ]);

        return $patient;
    }

    public function deactivate(Patient $patient): Patient
    {
        $patient->update([
            'is_active' => false,
            'updated_by' => auth()->id(),
        ]);

        event(new PatientDeactivated($patient));

        Log::info('Patient deactivated', [
            'patient_id' => $patient->id,
            'mrn' => $patient->mrn,
            'deactivated_by' => auth()->id(),
        ]);

        return $patient;
    }

    public function activate(Patient $patient): Patient
    {
        $patient->update([
            'is_active' => true,
            'updated_by' => auth()->id(),
        ]);

        Log::info('Patient activated', [
            'patient_id' => $patient->id,
            'mrn' => $patient->mrn,
            'activated_by' => auth()->id(),
        ]);

        return $patient;
    }

    public function markAsDeceased(Patient $patient, ?\DateTimeInterface $deceasedAt = null): Patient
    {
        $patient->update([
            'is_deceased' => true,
            'deceased_at' => $deceasedAt ?? now(),
            'is_active' => false,
            'updated_by' => auth()->id(),
        ]);

        Log::info('Patient marked as deceased', [
            'patient_id' => $patient->id,
            'mrn' => $patient->mrn,
            'deceased_at' => $patient->deceased_at,
        ]);

        return $patient;
    }

    public function linkUser(Patient $patient, int $userId): Patient
    {
        $patient->update([
            'user_id' => $userId,
            'updated_by' => auth()->id(),
        ]);

        return $patient;
    }

    public function unlinkUser(Patient $patient): Patient
    {
        $patient->update([
            'user_id' => null,
            'updated_by' => auth()->id(),
        ]);

        return $patient;
    }

    public function addSchool(Patient $patient, array $data): PatientSchool
    {
        return $this->schoolService->create($patient, $data);
    }

    public function updateSchool(Patient $patient, PatientSchool $school, array $data): PatientSchool
    {
        return $this->schoolService->update($school, $data);
    }

    public function deleteSchool(PatientSchool $school): bool
    {
        return $this->schoolService->delete($school);
    }

    public function setCurrentSchool(Patient $patient, PatientSchool $school): PatientSchool
    {
        return $this->schoolService->setCurrentSchool($patient, $school);
    }

    public function getDemographics(Patient $patient): array
    {
        return [
            'id' => $patient->id,
            'mrn' => $patient->mrn,
            'global_uuid' => $patient->global_uuid,
            'full_name' => $patient->full_name,
            'first_name' => $patient->first_name,
            'middle_name' => $patient->middle_name,
            'last_name' => $patient->last_name,
            'title' => $patient->title?->value,
            'gender' => $patient->gender?->value,
            'date_of_birth' => $patient->date_of_birth?->format('Y-m-d'),
            'age' => $patient->age,
            'blood_type' => $patient->blood_type?->value,
            'marital_status' => $patient->marital_status?->value,
            'nationality' => $patient->nationality,
            'occupation' => $patient->occupation,
            'phone' => mask_phone($patient->phone),
            'email' => mask_email($patient->email),
        ];
    }

    public function getFullProfile(Patient $patient): array
    {
        $patient->load([
            'branch',
            'identifiers',
            'emergencyContacts',
            'schools.currentSchool',
            'user',
        ]);

        return [
            'demographics' => $this->getDemographics($patient),
            'address' => $patient->address,
            'contact' => [
                'phone' => mask_phone($patient->phone),
                'email' => mask_email($patient->email),
                'preferred_language' => $patient->preferred_language,
            ],
            'identifiers' => $patient->identifiers->map(fn ($id) => [
                'type' => $id->type,
                'value' => $id->value,
                'issuer' => $id->issuer,
                'is_primary' => $id->is_primary,
                'is_verified' => $id->is_verified,
            ]),
            'emergency_contacts' => $patient->emergencyContacts->map(fn ($ec) => [
                'name' => $ec->name,
                'relationship' => $ec->relationship,
                'phone' => mask_phone($ec->phone),
            ]),
            'current_school' => $patient->currentSchool->first()?->display_name,
            'branch' => $patient->branch?->name,
            'registered_at' => $patient->created_at?->toIso8601String(),
        ];
    }

    protected function applyFilters($query, array $filters): void
    {
        if (! empty($filters['branch_id'])) {
            $query->where('branch_id', $filters['branch_id']);
        }

        if (! empty($filters['gender'])) {
            $query->where('gender', $filters['gender']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (! empty($filters['search'])) {
            $this->searchService->applyToQuery($query, $filters['search']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }
    }
}
