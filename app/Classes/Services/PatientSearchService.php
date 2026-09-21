<?php

namespace Modules\Patient\Classes\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Core\Contracts\ProvidesFilamentPatientSearch;
use Modules\Core\Support\BlindIndex;
use Modules\Core\Support\SuperAdmin;
use Modules\Patient\Models\Patient;

class PatientSearchService implements ProvidesFilamentPatientSearch
{
    /**
     * Plain columns matched with LIKE. Phone, email and identifier values are
     * encrypted at rest and are matched exactly through their blind indexes
     * instead (see applyBlindIndexSearch()).
     *
     * @var array<int, string>
     */
    protected array $searchableFields = [
        'mrn',
        'old_hospital_number',
        'first_name',
        'middle_name',
        'last_name',
        'global_uuid',
    ];

    /** @var array<int, string> */
    protected array $relationSearchableFields = [
        'identifiers.type',
        'emergencyContacts.name',
        'insurancePolicies.member_number',
    ];

    public function search(string $term, int $limit = 10): Collection
    {
        $term = $this->normalizeTerm($term);

        return Patient::query()
            ->with(['branch', 'identifiers'])
            ->whereNull('merged_into_patient_id')
            ->where(function ($query) use ($term) {
                $this->applySearch($query, $term);
            })
            ->orderByRaw("CASE
                WHEN mrn = ? OR old_hospital_number = ? THEN 0
                WHEN mrn LIKE ? OR old_hospital_number LIKE ? THEN 1
                WHEN CONCAT(first_name, ' ', last_name) LIKE ? THEN 2
                ELSE 3
            END", [$term, $term, "{$term}%", "{$term}%", "{$term}%"])
            ->limit($limit)
            ->get();
    }

    /**
     * An MRN that belonged to a merged duplicate resolves to the surviving profile.
     */
    public function searchExactMrn(string $mrn): ?Patient
    {
        $patient = Patient::withTrashed()->where('mrn', $mrn)->first();

        if ($patient === null) {
            return null;
        }

        if ($patient->isMerged()) {
            $survivor = app(PatientMergeService::class)->resolveSurvivor($patient);

            return $survivor->trashed() ? null : $survivor;
        }

        return $patient->trashed() ? null : $patient;
    }

    /**
     * Candidates a duplicate can be merged into: live, not themselves merged, and
     * not the record being merged. Super admins may pick across branches.
     */
    public function searchForMergeTarget(string $term, Patient $exclude, int $limit = 20): Collection
    {
        $term = $this->normalizeTerm($term);

        $query = Patient::query()
            ->whereKeyNot($exclude->getKey())
            ->whereNull('merged_into_patient_id')
            ->where(function ($query) use ($term) {
                $this->applySearch($query, $term);
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit($limit);

        if (SuperAdmin::check()) {
            $query->withoutGlobalScope('branch');
        }

        return $query->get();
    }

    /**
     * Exact match on the (encrypted) phone number in any common spelling.
     */
    public function searchByPhone(string $phone): Collection
    {
        if (BlindIndex::phone($phone) === null) {
            return new Collection;
        }

        return Patient::query()
            ->whereBlindIndex('phone', $phone)
            ->with(['branch'])
            ->get();
    }

    /**
     * Exact, case-insensitive match on the (encrypted) email address.
     */
    public function searchByEmail(string $email): Collection
    {
        if (BlindIndex::email($email) === null) {
            return new Collection;
        }

        return Patient::query()
            ->whereBlindIndex('email', $email)
            ->with(['branch'])
            ->get();
    }

    public function applyToQuery(Builder $query, string $term): Builder
    {
        $term = $this->normalizeTerm($term);

        return $query->where(function ($query) use ($term) {
            $this->applySearch($query, $term);
        });
    }

    public function applyFilters(Builder $query, array $filters): Builder
    {
        if (! empty($filters['gender'])) {
            $query->where('gender', $filters['gender']);
        }

        if (! empty($filters['branch_id'])) {
            $query->where('branch_id', $filters['branch_id']);
        }

        if (! empty($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $query;
    }

    public function getRecentPatients(int $limit = 10): Collection
    {
        return Patient::query()
            ->with(['branch'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    public function getPatientsWithoutIdentifiers(int $limit = 50): Collection
    {
        return Patient::query()
            ->whereDoesntHave('identifiers')
            ->with(['branch'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    public function getDuplicateCandidates(): Collection
    {
        return Patient::query()
            ->selectRaw('first_name, last_name, date_of_birth, COUNT(*) as count')
            ->whereNotNull('date_of_birth')
            ->groupBy('first_name', 'last_name', 'date_of_birth')
            ->having('count', '>', 1)
            ->get();
    }

    public function suggestSimilarPatients(string $name, int $limit = 5): Collection
    {
        $parts = explode(' ', $name, 2);

        return Patient::query()
            ->with(['branch'])
            ->where(function ($query) use ($parts) {
                if (count($parts) === 2) {
                    $query->where('first_name', 'LIKE', "%{$parts[0]}%")
                        ->where('last_name', 'LIKE', "%{$parts[1]}%");
                } else {
                    $query->where('first_name', 'LIKE', "%{$parts[0]}%")
                        ->orWhere('last_name', 'LIKE', "%{$parts[0]}%");
                }
            })
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    protected function applySearch(Builder $query, string $term): void
    {
        foreach ($this->searchableFields as $field) {
            $query->orWhere($field, 'LIKE', "%{$term}%");
        }

        $query->orWhereRaw(
            "CONCAT(first_name, ' ', COALESCE(middle_name, ''), ' ', last_name) LIKE ?",
            ["%{$term}%"]
        );

        $grouped = [];
        foreach ($this->relationSearchableFields as $field) {
            $parts = explode('.', $field, 2);
            if (count($parts) !== 2) {
                continue;
            }
            $grouped[$parts[0]][] = $parts[1];
        }

        foreach ($grouped as $relation => $columns) {
            if ($relation === 'insurancePolicies' && ! config('insurance.enabled', true)) {
                continue;
            }
            $query->orWhereHas($relation, function ($q) use ($columns, $term) {
                $first = array_shift($columns);
                $q->where($first, 'LIKE', "%{$term}%");
                foreach ($columns as $column) {
                    $q->orWhere($column, 'LIKE', "%{$term}%");
                }
            });
        }

        $this->applyBlindIndexSearch($query, $term);
    }

    /**
     * Encrypted contact details cannot be LIKE-matched; compare the term's
     * blind index with the patient's, the emergency contacts' and the
     * identifiers' stored hashes instead.
     */
    protected function applyBlindIndexSearch(Builder $query, string $term): void
    {
        if ($this->looksLikeEmail($term)) {
            $hash = BlindIndex::email($term);

            $query->orWhere('patients.email_index', $hash)
                ->orWhereHas('emergencyContacts', fn (Builder $contacts) => $contacts->where('email_index', $hash));

            return;
        }

        if ($this->looksLikePhone($term)) {
            $hash = BlindIndex::phone($term);

            $query->orWhere('patients.phone_index', $hash)
                ->orWhereHas('emergencyContacts', fn (Builder $contacts) => $contacts
                    ->where('phone_index', $hash)
                    ->orWhere('alternate_phone_index', $hash));
        }

        $identifierHash = BlindIndex::identifier($term);

        if ($identifierHash !== null) {
            $query->orWhereHas('identifiers', fn (Builder $identifiers) => $identifiers->where('value_index', $identifierHash));
        }
    }

    public function looksLikeEmail(string $term): bool
    {
        return filter_var(trim($term), FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * At least seven digits once separators and a leading plus are removed.
     */
    public function looksLikePhone(string $term): bool
    {
        return (bool) preg_match('/^\+?[\d\s\-().]{7,}$/', trim($term))
            && strlen($this->normalizePhone($term)) >= 7;
    }

    public function normalizeTerm(string $term): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', $term)));
    }

    public function normalizePhone(string $phone): string
    {
        return preg_replace('/\D/', '', $phone);
    }

    public function setSearchableFields(array $fields): void
    {
        $this->searchableFields = $fields;
    }

    public function addSearchableField(string $field): void
    {
        if (! in_array($field, $this->searchableFields)) {
            $this->searchableFields[] = $field;
        }
    }

    public function getSearchableFields(): array
    {
        return $this->searchableFields;
    }

    /**
     * Filament table column attributes for searching through a patient relation.
     *
     * @return array<int, string>
     */
    public function getFilamentRelationSearchableAttributes(string $relation = 'patient'): array
    {
        $attributes = array_map(
            fn (string $field): string => "{$relation}.{$field}",
            $this->searchableFields,
        );

        foreach ($this->relationSearchableFields as $field) {
            $attributes[] = "{$relation}.{$field}";
        }

        return $attributes;
    }

    /**
     * Filament table column attributes for searching a Patient query directly.
     *
     * @return array<int, string>
     */
    public function getFilamentSearchableAttributes(): array
    {
        return array_merge($this->searchableFields, $this->relationSearchableFields);
    }

    public function relationAttributes(string $relation): array
    {
        return $this->getFilamentRelationSearchableAttributes($relation);
    }

    public function directAttributes(): array
    {
        return $this->getFilamentSearchableAttributes();
    }

    /**
     * @return array<int, string>
     */
    public function getRelationSearchableFields(): array
    {
        return $this->relationSearchableFields;
    }
}
