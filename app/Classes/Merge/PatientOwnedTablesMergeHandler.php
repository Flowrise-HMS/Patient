<?php

namespace Modules\Patient\Classes\Merge;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Contracts\PatientMergeHandler;
use Modules\Patient\Models\EmergencyContact;
use Modules\Patient\Models\Patient;
use Modules\Patient\Models\PatientIdentifier;
use Modules\Patient\Models\PatientRelationship;
use Modules\Patient\Models\PatientSchool;

/**
 * Moves the Patient module's own child rows. These need care the generic
 * repoint cannot give: encrypted values are deduplicated in PHP, "primary" and
 * "current" flags are demoted, and the relationships unique key is respected.
 */
final class PatientOwnedTablesMergeHandler implements PatientMergeHandler
{
    public function handledTables(): array
    {
        return ['patient_identifiers', 'emergency_contacts', 'patient_schools', 'patient_relationships'];
    }

    public function preview(Model $source, Model $target): array
    {
        return [
            'Identifiers' => PatientIdentifier::query()->where('patient_id', $source->getKey())->count(),
            'Emergency contacts' => EmergencyContact::query()->where('patient_id', $source->getKey())->count(),
            'Schools' => PatientSchool::query()->where('patient_id', $source->getKey())->count(),
            'Relationships' => $this->relationshipsQuery($source)->count(),
        ];
    }

    public function handle(Model $source, Model $target, array $context): array
    {
        /** @var Patient $source */
        /** @var Patient $target */
        return [
            'patient_identifiers' => $this->moveIdentifiers($source, $target),
            'emergency_contacts' => $this->moveEmergencyContacts($source, $target),
            'patient_schools' => $this->moveSchools($source, $target),
            'patient_relationships' => $this->moveRelationships($source, $target),
        ];
    }

    /**
     * @return array{moved: int, deduped: int}
     */
    protected function moveIdentifiers(Patient $source, Patient $target): array
    {
        $existing = PatientIdentifier::query()->where('patient_id', $target->getKey())->get();
        $seen = $existing->map(fn (PatientIdentifier $identifier): string => $this->identifierKey($identifier))->flip();
        $primaryTypes = $existing->where('is_primary', true)->pluck('type')->map(fn ($type) => $this->enumValue($type))->flip();

        $moved = 0;
        $deduped = 0;

        foreach (PatientIdentifier::query()->where('patient_id', $source->getKey())->get() as $identifier) {
            $key = $this->identifierKey($identifier);

            if ($seen->has($key)) {
                $identifier->delete();
                $deduped++;

                continue;
            }

            $attributes = ['patient_id' => $target->getKey()];
            if ($identifier->is_primary && $primaryTypes->has($this->enumValue($identifier->type))) {
                $attributes['is_primary'] = false;
            }

            PatientIdentifier::query()->whereKey($identifier->getKey())->update($attributes);
            $seen->put($key, true);
            $moved++;
        }

        return ['moved' => $moved, 'deduped' => $deduped];
    }

    /**
     * @return array{moved: int, deduped: int}
     */
    protected function moveEmergencyContacts(Patient $source, Patient $target): array
    {
        $existing = EmergencyContact::query()->where('patient_id', $target->getKey())->get();
        $seen = $existing->map(fn (EmergencyContact $contact): string => $this->contactKey($contact))->flip();
        $targetHasPrimary = $existing->contains(fn (EmergencyContact $contact): bool => (bool) $contact->is_primary);

        $moved = 0;
        $deduped = 0;

        foreach (EmergencyContact::query()->where('patient_id', $source->getKey())->get() as $contact) {
            $key = $this->contactKey($contact);

            if ($seen->has($key)) {
                $contact->delete();
                $deduped++;

                continue;
            }

            $attributes = ['patient_id' => $target->getKey()];
            if ($contact->is_primary && $targetHasPrimary) {
                $attributes['is_primary'] = false;
            }

            EmergencyContact::query()->whereKey($contact->getKey())->update($attributes);
            $seen->put($key, true);
            $moved++;
        }

        return ['moved' => $moved, 'deduped' => $deduped];
    }

    /**
     * @return array{moved: int, deduped: int}
     */
    protected function moveSchools(Patient $source, Patient $target): array
    {
        $targetHasCurrent = PatientSchool::query()
            ->where('patient_id', $target->getKey())
            ->where('is_current', true)
            ->exists();

        $attributes = ['patient_id' => $target->getKey()];
        if ($targetHasCurrent) {
            $attributes['is_current'] = false;
        }

        $moved = PatientSchool::query()->where('patient_id', $source->getKey())->update($attributes);

        return ['moved' => $moved, 'deduped' => 0];
    }

    /**
     * @return array{moved: int, deduped: int}
     */
    protected function moveRelationships(Patient $source, Patient $target): array
    {
        $morph = $source->getMorphClass();
        $sourceId = (string) $source->getKey();
        $targetId = (string) $target->getKey();

        $moved = 0;
        $deduped = 0;

        foreach ($this->relationshipsQuery($source)->get() as $relationship) {
            $subjectId = $relationship->subject_type === $morph && (string) $relationship->subject_id === $sourceId ? $targetId : (string) $relationship->subject_id;
            $objectId = $relationship->object_type === $morph && (string) $relationship->object_id === $sourceId ? $targetId : (string) $relationship->object_id;

            $selfReferential = $relationship->subject_type === $morph
                && $relationship->object_type === $morph
                && $subjectId === $objectId;

            $collides = PatientRelationship::query()
                ->whereKeyNot($relationship->getKey())
                ->where('subject_type', $relationship->subject_type)
                ->where('subject_id', $subjectId)
                ->where('object_type', $relationship->object_type)
                ->where('object_id', $objectId)
                ->where('type', $this->enumValue($relationship->type))
                ->exists();

            if ($selfReferential || $collides) {
                $relationship->delete();
                $deduped++;

                continue;
            }

            PatientRelationship::query()->whereKey($relationship->getKey())->update([
                'subject_id' => $subjectId,
                'object_id' => $objectId,
            ]);
            $moved++;
        }

        return ['moved' => $moved, 'deduped' => $deduped];
    }

    protected function relationshipsQuery(Model $source): Builder
    {
        $morph = $source->getMorphClass();
        $id = (string) $source->getKey();

        return PatientRelationship::query()->where(function ($query) use ($morph, $id): void {
            $query->where(fn ($q) => $q->where('subject_type', $morph)->where('subject_id', $id))
                ->orWhere(fn ($q) => $q->where('object_type', $morph)->where('object_id', $id));
        });
    }

    protected function identifierKey(PatientIdentifier $identifier): string
    {
        return $this->enumValue($identifier->type).'|'.mb_strtolower(trim((string) $identifier->value));
    }

    protected function contactKey(EmergencyContact $contact): string
    {
        return mb_strtolower(trim((string) $contact->name)).'|'.preg_replace('/\D/', '', (string) $contact->phone);
    }

    protected function enumValue(mixed $value): string
    {
        return (string) ($value instanceof \BackedEnum ? $value->value : $value);
    }
}
