<?php

namespace Modules\Patient\Classes\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Patient\Models\Patient;

class PatientSearchService
{
    protected array $searchableFields = [
        'mrn',
        'first_name',
        'middle_name',
        'last_name',
        'phone',
        'email',
        'global_uuid',
    ];

    protected array $relationSearchableFields = [
        'identifiers.value',
        'identifiers.type',
        'emergencyContacts.phone',
        'emergencyContacts.name',
        'insurancePolicies.member_number',
    ];

    public function search(string $term, int $limit = 10): Collection
    {
        $term = $this->normalizeTerm($term);

        return Patient::query()
            ->with(['branch', 'identifiers'])
            ->where(function ($query) use ($term) {
                $this->applySearch($query, $term);
            })
            ->orderByRaw("CASE
                WHEN mrn = ? THEN 0
                WHEN mrn LIKE ? THEN 1
                WHEN CONCAT(first_name, ' ', last_name) LIKE ? THEN 2
                ELSE 3
            END", [$term, "{$term}%", "{$term}%"])
            ->limit($limit)
            ->get();
    }

    public function searchExactMrn(string $mrn): ?Patient
    {
        return Patient::where('mrn', $mrn)->first();
    }

    public function searchByPhone(string $phone): Collection
    {
        $normalizedPhone = $this->normalizePhone($phone);

        return Patient::query()
            ->whereRaw("REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '(', ''), ')', '') LIKE ?", ["%{$normalizedPhone}%"])
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
}
