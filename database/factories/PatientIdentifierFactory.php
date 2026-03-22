<?php

namespace Modules\Patient\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Patient\Enums\IdentifierType;
use Modules\Patient\Models\Patient;
use Modules\Patient\Models\PatientIdentifier;

/**
 * @extends Factory<PatientIdentifier>
 */
class PatientIdentifierFactory extends Factory
{
    protected $model = PatientIdentifier::class;

    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'type' => fake()->randomElement(IdentifierType::cases())->value,
            'value' => fake()->unique()->numerify('############'), // 12-digit number
            'issuer' => fake()->randomElement(['NIA', 'NHIA', 'GRA', 'EC', 'DVLA']),
            'issuer_country' => 'GH',
            'is_primary' => false,
            'is_verified' => false,
            'verified_at' => null,
            'verified_by' => null,
            'issue_date' => fake()->dateTimeBetween('-5 years', '-1 year'),
            'expiry_date' => fake()->dateTimeBetween('+1 year', '+10 years'),
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

    public function nationalId(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => IdentifierType::NATIONAL_ID->value,
            'issuer' => 'NIA',
            'value' => fake()->numerify('GHA##############'), // GhanaCard format
        ]);
    }

    public function nhis(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => IdentifierType::NHIS->value,
            'issuer' => 'NHIA',
            'value' => fake()->numerify('##########'),
        ]);
    }

    public function passport(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => IdentifierType::PASSPORT->value,
            'issuer_country' => fake()->countryISOAlpha3(),
            'value' => strtoupper(fake()->bothify('??########')),
        ]);
    }

    public function driversLicense(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => IdentifierType::DRIVERS_LICENSE->value,
            'issuer' => 'DVLA',
        ]);
    }

    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_verified' => true,
            'verified_at' => fake()->dateTimeBetween('-1 year', 'now'),
            'verified_by' => 1,
        ]);
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_verified' => false,
            'verified_at' => null,
            'verified_by' => null,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expiry_date' => fake()->dateTimeBetween('-2 years', '-1 day'),
        ]);
    }

    public function expiringSoon(int $days = 30): static
    {
        return $this->state(fn (array $attributes) => [
            'expiry_date' => fake()->dateTimeBetween('now', "+{$days} days"),
        ]);
    }

    public function forPatient(Patient $patient): static
    {
        return $this->state(fn (array $attributes) => [
            'patient_id' => $patient->id,
        ]);
    }
}
