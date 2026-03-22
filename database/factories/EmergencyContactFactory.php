<?php

namespace Modules\Patient\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Patient\Enums\RelationshipType;
use Modules\Patient\Models\EmergencyContact;
use Modules\Patient\Models\Patient;

/**
 * @extends Factory<EmergencyContact>
 */
class EmergencyContactFactory extends Factory
{
    protected $model = EmergencyContact::class;

    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'name' => fake()->name(),
            'relationship' => fake()->randomElement(RelationshipType::cases())->value,
            'relationship_other' => null,
            'phone' => fake()->phoneNumber(),
            'alternate_phone' => fake()->optional(0.3)->phoneNumber(),
            'email' => fake()->optional(0.5)->safeEmail(),
            'address' => fake()->address(),
            'is_primary' => true,
            'can_receive_sms' => true,
            'can_make_medical_decisions' => false,
            'note' => fake()->optional(0.2)->sentence(),
        ];
    }

    public function primary(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_primary' => true,
        ]);
    }

    public function secondary(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_primary' => false,
        ]);
    }

    public function spouse(): static
    {
        return $this->state(fn (array $attributes) => [
            'relationship' => RelationshipType::SPOUSE->value,
            'can_make_medical_decisions' => true,
        ]);
    }

    public function parent(): static
    {
        return $this->state(fn (array $attributes) => [
            'relationship' => RelationshipType::PARENT->value,
            'can_make_medical_decisions' => true,
        ]);
    }

    public function sibling(): static
    {
        return $this->state(fn (array $attributes) => [
            'relationship' => RelationshipType::SIBLING->value,
        ]);
    }

    public function child(): static
    {
        return $this->state(fn (array $attributes) => [
            'relationship' => RelationshipType::CHILD->value,
        ]);
    }

    public function withMedicalDecisionAuthority(): static
    {
        return $this->state(fn (array $attributes) => [
            'can_make_medical_decisions' => true,
        ]);
    }

    public function forPatient(Patient $patient): static
    {
        return $this->state(fn (array $attributes) => [
            'patient_id' => $patient->id,
        ]);
    }
}
