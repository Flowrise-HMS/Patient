<?php

namespace Modules\Patient\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Enums\Title;
use Modules\Patient\Enums\BloodType;
use Modules\Patient\Enums\EducationLevel;
use Modules\Patient\Enums\Gender;
use Modules\Patient\Enums\MaritalStatus;
use Modules\Patient\Models\Patient;

/**
 * @extends Factory<Patient>
 */
class PatientFactory extends Factory
{
    protected $model = Patient::class;

    public function definition(): array
    {
        $gender = fake()->randomElement(Gender::cases());
        $firstName = $gender === Gender::MALE
            ? fake()->firstNameMale()
            : fake()->firstNameFemale();

        return [
            'global_uuid' => fake()->uuid(),
            'branch_id' => null,
            'user_id' => null,
            'mrn' => null,
            'title' => fake()->randomElement([Title::cases()[0], Title::cases()[1], null]),
            'first_name' => $firstName,
            'middle_name' => fake()->optional(0.3)->firstName(),
            'last_name' => fake()->lastName(),
            'date_of_birth' => fake()->dateTimeBetween('-80 years', '-1 year'),
            'is_date_of_birth_estimated' => false,
            'gender' => $gender,
            'blood_type' => fake()->randomElement(BloodType::cases()),
            'marital_status' => fake()->randomElement(MaritalStatus::cases()),
            'education_level' => fake()->randomElement(EducationLevel::cases()),
            'occupation' => fake()->jobTitle(),
            'nationality' => 'GH',
            'address' => null,
            'contact' => [],
            'phone' => fake()->phoneNumber(),
            'email' => fake()->unique()->safeEmail(),
            'preferred_language' => fake()->randomElement(['english', 'twi', 'ga', 'ewe']),
            'is_active' => true,
            'is_deceased' => false,
            'deceased_at' => null,
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    public function male(): static
    {
        return $this->state(fn (array $attributes) => [
            'gender' => Gender::MALE,
            'first_name' => fake()->firstNameMale(),
            'title' => Title::MR,
        ]);
    }

    public function female(): static
    {
        return $this->state(fn (array $attributes) => [
            'gender' => Gender::FEMALE,
            'first_name' => fake()->firstNameFemale(),
            'title' => Title::MRS,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function deceased(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_deceased' => true,
            'is_active' => false,
            'deceased_at' => fake()->dateTimeBetween('-1 year', 'now'),
        ]);
    }

    public function estimatedDob(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_date_of_birth_estimated' => true,
        ]);
    }

    public function child(): static
    {
        return $this->state(fn (array $attributes) => [
            'date_of_birth' => fake()->dateTimeBetween('-17 years', '-1 year'),
            'marital_status' => MaritalStatus::SINGLE,
            'education_level' => EducationLevel::PRIMARY,
        ]);
    }

    public function neonate(): static
    {
        return $this->state(fn (array $attributes) => [
            'date_of_birth' => fake()->dateTimeBetween('-1 month', 'now'),
            'is_date_of_birth_estimated' => fake()->boolean(30),
        ]);
    }

    public function withMrn(string $mrn): static
    {
        return $this->state(fn (array $attributes) => [
            'mrn' => $mrn,
        ]);
    }

    public function withBranch(int $branchId): static
    {
        return $this->state(fn (array $attributes) => [
            'branch_id' => $branchId,
        ]);
    }

    public function withUser(int $userId): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $userId,
        ]);
    }
}
