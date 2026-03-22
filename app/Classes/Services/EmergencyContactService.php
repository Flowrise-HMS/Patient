<?php

namespace Modules\Patient\Classes\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Patient\Enums\RelationshipType;
use Modules\Patient\Models\EmergencyContact;
use Modules\Patient\Models\Patient;

class EmergencyContactService
{
    public function all(Patient $patient): Collection
    {
        return $patient->emergencyContacts()
            ->orderByDesc('is_primary')
            ->orderBy('created_at')
            ->get();
    }

    public function find(string $id): ?EmergencyContact
    {
        return EmergencyContact::find($id);
    }

    public function findByPatientAndRelationship(Patient $patient, string|RelationshipType $relationship): ?EmergencyContact
    {
        $relationshipValue = $relationship instanceof RelationshipType ? $relationship->value : $relationship;

        return $patient->emergencyContacts()
            ->where('relationship', $relationshipValue)
            ->first();
    }

    public function getPrimaryContact(Patient $patient): ?EmergencyContact
    {
        return $patient->emergencyContacts()
            ->where('is_primary', true)
            ->first();
    }

    public function create(Patient $patient, array $data): EmergencyContact
    {
        return DB::transaction(function () use ($patient, $data) {
            if (($data['is_primary'] ?? false)) {
                $this->clearPrimaryFlag($patient);
            }

            $data['patient_id'] = $patient->id;

            $contact = $patient->emergencyContacts()->create($data);

            Log::info('Emergency contact created', [
                'contact_id' => $contact->id,
                'patient_id' => $patient->id,
                'relationship' => $contact->relationship,
            ]);

            return $contact;
        });
    }

    public function update(EmergencyContact $contact, array $data): EmergencyContact
    {
        return DB::transaction(function () use ($contact, $data) {
            if (($data['is_primary'] ?? false)) {
                $this->clearPrimaryFlag($contact->patient);
            }

            $contact->update($data);

            Log::info('Emergency contact updated', [
                'contact_id' => $contact->id,
                'patient_id' => $contact->patient_id,
            ]);

            return $contact->fresh();
        });
    }

    public function updatePrimaryContact(Patient $patient, array $data): EmergencyContact
    {
        return DB::transaction(function () use ($patient, $data) {
            $primary = $this->getPrimaryContact($patient);

            if ($primary) {
                $primary->update($data);

                return $primary->fresh();
            }

            $data['is_primary'] = true;

            return $this->create($patient, $data);
        });
    }

    public function delete(EmergencyContact $contact): bool
    {
        $wasPrimary = $contact->is_primary;
        $patientId = $contact->patient_id;
        $contactId = $contact->id;

        $deleted = $contact->delete();

        if ($deleted && $wasPrimary) {
            $this->promoteNextPrimary($patientId);
        }

        Log::info('Emergency contact deleted', [
            'contact_id' => $contactId,
            'patient_id' => $patientId,
            'was_primary' => $wasPrimary,
        ]);

        return $deleted;
    }

    public function setAsPrimary(EmergencyContact $contact): EmergencyContact
    {
        return DB::transaction(function () use ($contact) {
            $this->clearPrimaryFlag($contact->patient);
            $contact->update(['is_primary' => true]);

            Log::info('Emergency contact set as primary', [
                'contact_id' => $contact->id,
                'patient_id' => $contact->patient_id,
            ]);

            return $contact->fresh();
        });
    }

    public function hasMedicalDecisionMaker(Patient $patient): bool
    {
        return $patient->emergencyContacts()
            ->where('can_make_medical_decisions', true)
            ->exists();
    }

    public function getMedicalDecisionMakers(Patient $patient): Collection
    {
        return $patient->emergencyContacts()
            ->where('can_make_medical_decisions', true)
            ->get();
    }

    public function getByRelationship(Patient $patient, string|RelationshipType $relationship): Collection
    {
        $relationshipValue = $relationship instanceof RelationshipType ? $relationship->value : $relationship;

        return $patient->emergencyContacts()
            ->where('relationship', $relationshipValue)
            ->get();
    }

    public function getImmediateFamilyContacts(Patient $patient): Collection
    {
        $immediateFamilyRelationships = [
            RelationshipType::SPOUSE->value,
            RelationshipType::PARENT->value,
            RelationshipType::CHILD->value,
            RelationshipType::SIBLING->value,
        ];

        return $patient->emergencyContacts()
            ->whereIn('relationship', $immediateFamilyRelationships)
            ->get();
    }

    public function canReceiveSms(Patient $patient): Collection
    {
        return $patient->emergencyContacts()
            ->where('can_receive_sms', true)
            ->whereNotNull('phone')
            ->get();
    }

    public function bulkCreate(Patient $patient, array $contacts): Collection
    {
        return DB::transaction(function () use ($patient, $contacts) {
            $created = collect();

            $first = true;
            foreach ($contacts as $data) {
                if (! empty($data['name']) && ! empty($data['phone'])) {
                    if ($first && ! isset($data['is_primary'])) {
                        $data['is_primary'] = true;
                        $first = false;
                    }

                    $created->push($this->create($patient, $data));
                }
            }

            return $created;
        });
    }

    public function reorderPriority(Patient $patient, array $contactIds): void
    {
        DB::transaction(function () use ($patient, $contactIds) {
            $this->clearPrimaryFlag($patient);

            foreach ($contactIds as $index => $id) {
                $isPrimary = $index === 0;
                EmergencyContact::where('id', $id)
                    ->where('patient_id', $patient->id)
                    ->update([
                        'is_primary' => $isPrimary,
                    ]);
            }
        });
    }

    public function toArray(EmergencyContact $contact, bool $maskSensitive = true): array
    {
        return [
            'id' => $contact->id,
            'name' => $contact->name,
            'relationship' => $contact->relationship,
            'relationship_other' => $contact->relationship_other,
            'phone' => $maskSensitive ? mask_phone($contact->phone) : $contact->phone,
            'alternate_phone' => $maskSensitive ? mask_phone($contact->alternate_phone) : $contact->alternate_phone,
            'email' => $maskSensitive ? mask_email($contact->email) : $contact->email,
            'address' => $contact->address,
            'is_primary' => $contact->is_primary,
            'can_receive_sms' => $contact->can_receive_sms,
            'can_make_medical_decisions' => $contact->can_make_medical_decisions,
            'note' => $contact->note,
        ];
    }

    protected function clearPrimaryFlag(Patient $patient): void
    {
        $patient->emergencyContacts()
            ->where('is_primary', true)
            ->update(['is_primary' => false]);
    }

    protected function promoteNextPrimary(int $patientId): void
    {
        $next = EmergencyContact::where('patient_id', $patientId)
            ->orderBy('created_at')
            ->first();

        if ($next) {
            $next->update(['is_primary' => true]);
        }
    }
}
