<?php

namespace Modules\Patient\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Patient\Enums\PatientRelationshipType;
use Modules\Patient\Models\Patient;
use Modules\Patient\Models\PatientRelationship;

class PatientRelationshipFactory extends Factory
{
    protected $model = PatientRelationship::class;

    public function definition(): array
    {
        return [
            'subject_type' => Patient::class,
            'subject_id' => Patient::factory(),
            'object_type' => Patient::class,
            'object_id' => Patient::factory(),
            'type' => PatientRelationshipType::MOTHER,
        ];
    }
}
