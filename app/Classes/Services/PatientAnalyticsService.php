<?php

namespace Modules\Patient\Classes\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Clinical\Models\EncounterDiagnosis;
use Modules\Patient\Models\Patient;

class PatientAnalyticsService
{
    /**
     * @return array{today: int, this_week: int, this_month: int, total_active: int}
     */
    public function getRegistrationSummary(?string $branchId = null): array
    {
        $base = $this->patientsQuery($branchId);

        return [
            'today' => (clone $base)->whereDate('created_at', today())->count(),
            'this_week' => (clone $base)->where('created_at', '>=', now()->startOfWeek())->count(),
            'this_month' => (clone $base)->where('created_at', '>=', now()->startOfMonth())->count(),
            'total_active' => (clone $base)->where('is_active', true)->where('is_deceased', false)->count(),
        ];
    }

    /**
     * @return list<array{id: string, mrn: string|null, name: string, gender: string|null, region: string|null, created_at: string}>
     */
    public function getRecentRegistrations(int $limit = 10, ?string $branchId = null): array
    {
        return $this->patientsQuery($branchId)
            ->where('created_at', '>=', now()->subDays(7))
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get(['id', 'mrn', 'first_name', 'middle_name', 'last_name', 'title', 'gender', 'address', 'created_at'])
            ->map(function (Patient $patient): array {
                return [
                    'id' => (string) $patient->id,
                    'mrn' => $patient->mrn,
                    'name' => $patient->full_name,
                    'gender' => $patient->gender?->value ?? (is_string($patient->gender) ? $patient->gender : null),
                    'region' => $this->resolveRegionLabel($patient->address ?? []),
                    'created_at' => $patient->created_at?->toIso8601String() ?? '',
                ];
            })
            ->all();
    }

    /**
     * @return array{labels: list<string>, counts: list<int>}
     */
    public function getMonthlyRegistrations(int $months = 12, ?string $branchId = null): array
    {
        $labels = [];
        $counts = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $labels[] = $month->format('M Y');
            $counts[] = $this->patientsQuery($branchId)
                ->whereYear('created_at', $month->year)
                ->whereMonth('created_at', $month->month)
                ->count();
        }

        return ['labels' => $labels, 'counts' => $counts];
    }

    /**
     * @return array{labels: list<string>, counts: list<int>}
     */
    public function getYearlyRegistrations(int $years = 5, ?string $branchId = null): array
    {
        $labels = [];
        $counts = [];

        for ($i = $years - 1; $i >= 0; $i--) {
            $year = now()->subYears($i)->year;
            $labels[] = (string) $year;
            $counts[] = $this->patientsQuery($branchId)
                ->whereYear('created_at', $year)
                ->count();
        }

        return ['labels' => $labels, 'counts' => $counts];
    }

    /**
     * @return array{labels: list<string>, counts: list<int>}
     */
    public function getPatientsByRegion(int $limit = 10, ?string $branchId = null): array
    {
        $regionExpression = $this->regionSqlExpression();

        $subQuery = $this->patientsQuery($branchId)
            ->selectRaw("{$regionExpression} as region_label");

        $rows = DB::query()
            ->fromSub($subQuery, 'patient_regions')
            ->selectRaw('region_label, count(*) as patient_count')
            ->groupBy('region_label')
            ->orderByDesc('patient_count')
            ->limit($limit)
            ->get();

        return [
            'labels' => $rows->pluck('region_label')->map(fn ($label) => (string) $label)->all(),
            'counts' => $rows->pluck('patient_count')->map(fn ($count) => (int) $count)->all(),
        ];
    }

    /**
     * @return array{labels: list<string>, counts: list<int>}
     */
    public function getTopDiagnoses(int $limit = 8, int $days = 90, ?string $branchId = null): array
    {
        $labelExpression = match (DB::connection()->getDriverName()) {
            'sqlite' => "COALESCE(NULLIF(diagnosis_codes.description, ''), NULLIF(encounter_diagnoses.description, ''), encounter_diagnoses.icd_code, 'Unknown')",
            'pgsql' => "COALESCE(NULLIF(diagnosis_codes.description, ''), NULLIF(encounter_diagnoses.description, ''), encounter_diagnoses.icd_code, 'Unknown')",
            default => "COALESCE(NULLIF(diagnosis_codes.description, ''), NULLIF(encounter_diagnoses.description, ''), encounter_diagnoses.icd_code, 'Unknown')",
        };

        $subQuery = EncounterDiagnosis::query()
            ->join('patients', 'patients.id', '=', 'encounter_diagnoses.patient_id')
            ->leftJoin('diagnosis_codes', 'diagnosis_codes.id', '=', 'encounter_diagnoses.diagnosis_code_id')
            ->when($branchId, fn ($query) => $query->where('patients.branch_id', $branchId))
            ->where('encounter_diagnoses.created_at', '>=', now()->subDays($days))
            ->selectRaw("{$labelExpression} as diagnosis_label");

        $rows = DB::query()
            ->fromSub($subQuery, 'diagnosis_labels')
            ->selectRaw('diagnosis_label, count(*) as diagnosis_count')
            ->groupBy('diagnosis_label')
            ->orderByDesc('diagnosis_count')
            ->limit($limit)
            ->get();

        return [
            'labels' => $rows->pluck('diagnosis_label')->map(fn ($label) => (string) $label)->all(),
            'counts' => $rows->pluck('diagnosis_count')->map(fn ($count) => (int) $count)->all(),
        ];
    }

    protected function patientsQuery(?string $branchId): Builder
    {
        return Patient::query()
            ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId));
    }

    protected function regionSqlExpression(): string
    {
        $region = $this->addressJsonPath('region');
        $district = $this->addressJsonPath('district');
        $city = $this->addressJsonPath('city');

        return "COALESCE(NULLIF({$region}, ''), NULLIF({$district}, ''), NULLIF({$city}, ''), 'Unknown')";
    }

    protected function addressJsonPath(string $key): string
    {
        return match (DB::connection()->getDriverName()) {
            'pgsql' => "address->>'{$key}'",
            'mysql', 'mariadb' => "JSON_UNQUOTE(JSON_EXTRACT(address, '$.{$key}'))",
            default => "json_extract(address, '$.{$key}')",
        };
    }

    /**
     * @param  array<string, mixed>  $address
     */
    protected function resolveRegionLabel(array $address): ?string
    {
        foreach (['region', 'district', 'city'] as $key) {
            $value = $address[$key] ?? null;
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }
}
