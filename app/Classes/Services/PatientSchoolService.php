<?php

namespace Modules\Patient\Classes\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Patient\Models\Patient;
use Modules\Patient\Models\PatientSchool;

class PatientSchoolService
{
    public function all(Patient $patient): Collection
    {
        return $patient->schools()
            ->orderByDesc('is_current')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function find(string $id): ?PatientSchool
    {
        return PatientSchool::find($id);
    }

    public function findByPatient(Patient $patient, string $id): ?PatientSchool
    {
        return $patient->schools()->find($id);
    }

    public function getCurrentSchool(Patient $patient): ?PatientSchool
    {
        return $patient->schools()
            ->where('is_current', true)
            ->first();
    }

    public function getActiveSchools(Patient $patient): Collection
    {
        return $patient->schools()
            ->where('is_active', true)
            ->orderByDesc('is_current')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getByLevel(Patient $patient, string $level): Collection
    {
        return $patient->schools()
            ->where('level', $level)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getByType(Patient $patient, string $type): Collection
    {
        return $patient->schools()
            ->where('school_type', $type)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function create(Patient $patient, array $data): PatientSchool
    {
        return DB::transaction(function () use ($patient, $data) {
            if (($data['is_current'] ?? false)) {
                $this->clearCurrentFlag($patient);
            }

            $data['patient_id'] = $patient->id;
            $data['created_by'] = auth()->id();

            $school = $patient->schools()->create($data);

            Log::info('Patient school created', [
                'school_id' => $school->id,
                'patient_id' => $patient->id,
                'school_name' => $school->school_name,
                'school_type' => $school->school_type,
            ]);

            return $school;
        });
    }

    public function update(PatientSchool $school, array $data): PatientSchool
    {
        return DB::transaction(function () use ($school, $data) {
            if (($data['is_current'] ?? false)) {
                $this->clearCurrentFlag($school->patient);
            }

            $data['updated_by'] = auth()->id();

            $school->update($data);

            Log::info('Patient school updated', [
                'school_id' => $school->id,
                'patient_id' => $school->patient_id,
            ]);

            return $school->fresh();
        });
    }

    public function delete(PatientSchool $school): bool
    {
        $wasCurrent = $school->is_current;
        $patientId = $school->patient_id;
        $schoolId = $school->id;

        $deleted = $school->delete();

        if ($deleted && $wasCurrent) {
            $this->promoteNextCurrent($patientId);
        }

        Log::info('Patient school deleted', [
            'school_id' => $schoolId,
            'patient_id' => $patientId,
            'was_current' => $wasCurrent,
        ]);

        return $deleted;
    }

    public function setAsCurrent(PatientSchool $school): PatientSchool
    {
        return DB::transaction(function () use ($school) {
            $this->clearCurrentFlag($school->patient);
            $school->update(['is_current' => true]);

            Log::info('Patient school set as current', [
                'school_id' => $school->id,
                'patient_id' => $school->patient_id,
            ]);

            return $school->fresh();
        });
    }

    public function setCurrentSchool(Patient $patient, PatientSchool $school): PatientSchool
    {
        return DB::transaction(function () use ($patient, $school) {
            $this->clearCurrentFlag($patient);
            $school->update(['is_current' => true]);

            Log::info('Patient current school changed', [
                'school_id' => $school->id,
                'patient_id' => $patient->id,
            ]);

            return $school->fresh();
        });
    }

    public function completeCurrentSchool(Patient $patient, ?\DateTimeInterface $graduationDate = null): ?PatientSchool
    {
        $currentSchool = $this->getCurrentSchool($patient);

        if (! $currentSchool) {
            return null;
        }

        $currentSchool->update([
            'is_current' => false,
            'graduation_date' => $graduationDate ?? now(),
            'is_active' => false,
            'updated_by' => auth()->id(),
        ]);

        Log::info('Patient school completed', [
            'school_id' => $currentSchool->id,
            'patient_id' => $patient->id,
            'graduation_date' => $currentSchool->graduation_date,
        ]);

        return $currentSchool;
    }

    public function transitionToNewSchool(
        Patient $patient,
        array $newSchoolData,
        ?\DateTimeInterface $graduationDate = null
    ): PatientSchool {
        return DB::transaction(function () use ($patient, $newSchoolData, $graduationDate) {
            $this->completeCurrentSchool($patient, $graduationDate);

            $newSchoolData['is_current'] = true;

            return $this->create($patient, $newSchoolData);
        });
    }

    public function isCurrentlyEnrolled(Patient $patient): bool
    {
        return $patient->schools()
            ->where('is_current', true)
            ->where('is_active', true)
            ->exists();
    }

    public function getEducationTimeline(Patient $patient): Collection
    {
        return $patient->schools()
            ->orderBy('admission_date', 'asc')
            ->orderBy('created_at', 'asc')
            ->get();
    }

    public function getHighestLevel(Patient $patient): ?string
    {
        return $patient->schools()
            ->whereNotNull('level')
            ->orderByRaw("FIELD(level, 'primary', 'jhs', 'shs', 'tertiary', 'university', 'postgraduate') DESC")
            ->value('level');
    }

    public function bulkCreate(Patient $patient, array $schools): Collection
    {
        return DB::transaction(function () use ($patient, $schools) {
            $created = collect();
            $first = true;

            foreach ($schools as $data) {
                if (! empty($data['school_name'])) {
                    if ($first) {
                        $data['is_current'] = $data['is_current'] ?? true;
                        $first = false;
                    } else {
                        $data['is_current'] = $data['is_current'] ?? false;
                    }

                    $created->push($this->create($patient, $data));
                }
            }

            return $created;
        });
    }

    public function toArray(PatientSchool $school, bool $maskSensitive = true): array
    {
        return [
            'id' => $school->id,
            'school_name' => $school->school_name,
            'school_id' => $school->school_id,
            'school_type' => $school->school_type,
            'school_address' => $school->school_address,
            'school_phone' => $maskSensitive ? mask_phone($school->school_phone) : $school->school_phone,
            'school_email' => $school->school_email,
            'level' => $school->level,
            'class_name' => $school->class_name,
            'classroom' => $school->classroom,
            'hostel' => $school->hostel,
            'hostel_room' => $school->hostel_room,
            'course' => $school->course,
            'year_of_study' => $school->year_of_study,
            'admission_date' => $school->admission_date?->format('Y-m-d'),
            'graduation_date' => $school->graduation_date?->format('Y-m-d'),
            'is_current' => $school->is_current,
            'is_active' => $school->is_active,
            'display_name' => $school->display_name,
            'notes' => $school->notes,
        ];
    }

    protected function clearCurrentFlag(Patient $patient): void
    {
        $patient->schools()
            ->where('is_current', true)
            ->update(['is_current' => false]);
    }

    protected function promoteNextCurrent(string $patientId): void
    {
        $next = PatientSchool::where('patient_id', $patientId)
            ->where('is_active', true)
            ->orderBy('created_at', 'desc')
            ->first();

        if ($next) {
            $next->update(['is_current' => true]);
        }
    }
}
