<?php

namespace Modules\Patient\Classes\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Patient\Enums\IdentifierType;
use Modules\Patient\Models\Patient;
use Modules\Patient\Models\PatientIdentifier;

class PatientIdentifierService
{
    public function all(Patient $patient): Collection
    {
        return $patient->identifiers()->orderByDesc('is_primary')->orderBy('created_at')->get();
    }

    public function find(string $id): ?PatientIdentifier
    {
        return PatientIdentifier::find($id);
    }

    public function findByType(Patient $patient, string|IdentifierType $type): ?PatientIdentifier
    {
        $typeValue = $type instanceof IdentifierType ? $type->value : $type;

        return $patient->identifiers()->where('type', $typeValue)->first();
    }

    public function findByValue(string $value): ?PatientIdentifier
    {
        return PatientIdentifier::where('value', $value)->first();
    }

    public function getPrimaryMrn(Patient $patient): ?PatientIdentifier
    {
        return $patient->identifiers()
            ->where('type', IdentifierType::MRN->value)
            ->where('is_primary', true)
            ->first();
    }

    public function getPrimaryIdentifier(Patient $patient): ?PatientIdentifier
    {
        return $patient->identifiers()->where('is_primary', true)->first();
    }

    public function create(Patient $patient, array $data): PatientIdentifier
    {
        return DB::transaction(function () use ($patient, $data) {
            if (($data['is_primary'] ?? false)) {
                $this->clearPrimaryFlag($patient);
            }

            $data['patient_id'] = $patient->id;
            $data['created_by'] = auth()->id();

            $identifier = $patient->identifiers()->create($data);

            Log::info('Patient identifier created', [
                'patient_id' => $patient->id,
                'identifier_id' => $identifier->id,
                'type' => $identifier->type,
            ]);

            return $identifier;
        });
    }

    public function update(PatientIdentifier $identifier, array $data): PatientIdentifier
    {
        return DB::transaction(function () use ($identifier, $data) {
            if (($data['is_primary'] ?? false)) {
                $this->clearPrimaryFlag($identifier->patient, $identifier->type);
            }

            $data['updated_by'] = auth()->id();

            $identifier->update($data);

            Log::info('Patient identifier updated', [
                'identifier_id' => $identifier->id,
                'patient_id' => $identifier->patient_id,
                'type' => $identifier->type,
            ]);

            return $identifier->fresh();
        });
    }

    public function delete(PatientIdentifier $identifier): bool
    {
        $wasPrimary = $identifier->is_primary;
        $patientId = $identifier->patient_id;
        $type = $identifier->type;

        $deleted = $identifier->delete();

        if ($deleted && $wasPrimary) {
            $this->promoteNextPrimary($identifier->patient, $type);
        }

        Log::info('Patient identifier deleted', [
            'patient_id' => $patientId,
            'type' => $type,
            'was_primary' => $wasPrimary,
        ]);

        return $deleted;
    }

    public function setAsPrimary(PatientIdentifier $identifier): PatientIdentifier
    {
        return DB::transaction(function () use ($identifier) {
            $this->clearPrimaryFlag($identifier->patient, $identifier->type);

            $identifier->update(['is_primary' => true]);

            Log::info('Patient identifier set as primary', [
                'identifier_id' => $identifier->id,
                'patient_id' => $identifier->patient_id,
                'type' => $identifier->type,
            ]);

            return $identifier->fresh();
        });
    }

    public function verify(PatientIdentifier $identifier): PatientIdentifier
    {
        $identifier->update([
            'is_verified' => true,
            'verified_at' => now(),
            'verified_by' => auth()->id(),
        ]);

        Log::info('Patient identifier verified', [
            'identifier_id' => $identifier->id,
            'patient_id' => $identifier->patient_id,
            'verified_by' => auth()->id(),
        ]);

        return $identifier;
    }

    public function unverify(PatientIdentifier $identifier): PatientIdentifier
    {
        $identifier->update([
            'is_verified' => false,
            'verified_at' => null,
        ]);

        return $identifier;
    }

    public function isUnique(string $value, ?string $excludeId = null): bool
    {
        $query = PatientIdentifier::where('value', $value);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return ! $query->exists();
    }

    public function findDuplicate(string $value): ?PatientIdentifier
    {
        return PatientIdentifier::where('value', $value)->first();
    }

    public function getExpiringSoon(int $days = 30): Collection
    {
        return PatientIdentifier::query()
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<=', now()->addDays($days))
            ->whereDate('expiry_date', '>=', now())
            ->with('patient')
            ->get();
    }

    public function getExpired(): Collection
    {
        return PatientIdentifier::query()
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<', now())
            ->with('patient')
            ->get();
    }

    public function getUnverified(int $days = 7): Collection
    {
        return PatientIdentifier::query()
            ->where('is_verified', false)
            ->whereDate('created_at', '<=', now()->subDays($days))
            ->with('patient')
            ->get();
    }

    public function bulkCreate(Patient $patient, array $identifiers): \Illuminate\Support\Collection
    {
        return DB::transaction(function () use ($patient, $identifiers) {
            $created = collect();

            foreach ($identifiers as $data) {
                if (! empty($data['type']) && ! empty($data['value'])) {
                    $created->push($this->create($patient, $data));
                }
            }

            return $created;
        });
    }

    public function bulkVerify(array $ids): int
    {
        return PatientIdentifier::whereIn('id', $ids)
            ->where('is_verified', false)
            ->update([
                'is_verified' => true,
                'verified_at' => now(),
                'verified_by' => auth()->id(),
            ]);
    }

    protected function clearPrimaryFlag(Patient $patient, ?string $type = null): void
    {
        $query = $patient->identifiers()->where('is_primary', true);

        if ($type) {
            $query->where('type', $type);
        }

        $query->update(['is_primary' => false]);
    }

    protected function promoteNextPrimary(Patient $patient, string $type): void
    {
        $next = $patient->identifiers()
            ->where('type', $type)
            ->orderBy('created_at')
            ->first();

        if ($next) {
            $next->update(['is_primary' => true]);
        }
    }

    public function toArray(PatientIdentifier $identifier, bool $maskSensitive = true): array
    {
        $data = [
            'id' => $identifier->id,
            'type' => $identifier->type,
            'value' => $maskSensitive ? mask_national_id($identifier->value) : $identifier->value,
            'issuer' => $identifier->issuer,
            'issuer_country' => $identifier->issuer_country,
            'is_primary' => $identifier->is_primary,
            'is_verified' => $identifier->is_verified,
            'verified_at' => $identifier->verified_at?->toIso8601String(),
            'issue_date' => $identifier->issue_date?->format('Y-m-d'),
            'expiry_date' => $identifier->expiry_date?->format('Y-m-d'),
        ];

        return $data;
    }
}
