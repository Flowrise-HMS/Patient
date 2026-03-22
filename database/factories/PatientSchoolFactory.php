<?php

namespace Modules\Patient\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Patient\Enums\SchoolType;
use Modules\Patient\Models\Patient;
use Modules\Patient\Models\PatientSchool;

/**
 * @extends Factory<PatientSchool>
 */
class PatientSchoolFactory extends Factory
{
    protected $model = PatientSchool::class;

    public function definition(): array
    {
        $schoolType = fake()->randomElement(SchoolType::cases());
        $levels = $schoolType->getClassLevels();
        $classLevel = ! empty($levels) ? fake()->randomElement($levels) : null;

        return [
            'patient_id' => Patient::factory(),
            'school_name' => fake()->company().' '.fake()->randomElement(['School', 'College', 'Institute', 'University']),
            'school_id' => fake()->optional(0.3)->numerify('SCH-#####'),
            'school_address' => fake()->address(),
            'school_phone' => fake()->optional(0.7)->phoneNumber(),
            'school_email' => fake()->optional(0.5)->companyEmail(),
            'school_type' => $schoolType->value,
            'level' => $classLevel,
            'class_name' => $classLevel,
            'classroom' => fake()->optional(0.3)->numerify('Class ##'),
            'hostel' => $schoolType->requiresHostel() && fake()->boolean(40) ? 'Yes' : 'No',
            'hostel_room' => fake()->optional(0.3)->numerify('Room ###'),
            'course' => $schoolType->requiresCourse() ? fake()->randomElement([
                'Computer Science',
                'Medicine',
                'Engineering',
                'Business Administration',
                'Law',
                'Nursing',
            ]) : null,
            'course_duration' => $schoolType->requiresCourse() ? fake()->numberBetween(1, 6) : null,
            'year_of_study' => $schoolType->requiresCourse() ? fake()->numberBetween(1, 6) : null,
            'admission_date' => fake()->dateTimeBetween('-6 years', '-1 year'),
            'graduation_date' => null,
            'is_current' => true,
            'is_active' => true,
            'notes' => fake()->optional(0.2)->sentence(),
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    public function current(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_current' => true,
            'is_active' => true,
            'graduation_date' => null,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_current' => false,
            'is_active' => false,
            'graduation_date' => fake()->dateTimeBetween('-1 year', 'now'),
        ]);
    }

    public function primary(): static
    {
        return $this->state(fn (array $attributes) => [
            'school_type' => SchoolType::PRIMARY->value,
            'level' => fake()->randomElement(['Primary 1', 'Primary 2', 'Primary 3', 'Primary 4', 'Primary 5', 'Primary 6']),
            'class_name' => null,
            'course' => null,
        ]);
    }

    public function jhs(): static
    {
        return $this->state(fn (array $attributes) => [
            'school_type' => SchoolType::JUNIOR_HIGH->value,
            'level' => fake()->randomElement(['JHS 1', 'JHS 2', 'JHS 3']),
            'class_name' => null,
            'course' => null,
        ]);
    }

    public function shs(): static
    {
        return $this->state(fn (array $attributes) => [
            'school_type' => SchoolType::SENIOR_HIGH->value,
            'level' => fake()->randomElement(['SHS 1', 'SHS 2', 'SHS 3']),
            'class_name' => null,
            'course' => null,
            'hostel' => fake()->boolean(30) ? 'Yes' : 'No',
        ]);
    }

    public function university(): static
    {
        $years = fake()->numberBetween(1, 6);

        return $this->state(fn (array $attributes) => [
            'school_type' => SchoolType::UNIVERSITY->value,
            'level' => "Year {$years}",
            'class_name' => null,
            'course' => fake()->randomElement([
                'Computer Science',
                'Medicine',
                'Engineering',
                'Business Administration',
                'Law',
                'Nursing',
                'Pharmacy',
            ]),
            'course_duration' => 4,
            'year_of_study' => $years,
            'hostel' => fake()->boolean(60) ? 'Yes' : 'No',
        ]);
    }

    public function tertiary(): static
    {
        $years = fake()->numberBetween(1, 3);

        return $this->state(fn (array $attributes) => [
            'school_type' => SchoolType::TERTIARY->value,
            'level' => "Year {$years}",
            'class_name' => null,
            'course' => fake()->randomElement([
                'HND in Accounting',
                'HND in Business Studies',
                'HND in Computer Science',
                'Diploma in Nursing',
            ]),
            'course_duration' => 3,
            'year_of_study' => $years,
        ]);
    }

    public function withHostel(): static
    {
        return $this->state(fn (array $attributes) => [
            'hostel' => 'Yes',
            'hostel_room' => fake()->numerify('Room ###'),
        ]);
    }

    public function withoutHostel(): static
    {
        return $this->state(fn (array $attributes) => [
            'hostel' => 'No',
            'hostel_room' => null,
        ]);
    }

    public function forPatient(Patient $patient): static
    {
        return $this->state(fn (array $attributes) => [
            'patient_id' => $patient->id,
        ]);
    }

    public function admittedOn(\DateTimeInterface $date): static
    {
        return $this->state(fn (array $attributes) => [
            'admission_date' => $date,
        ]);
    }

    public function graduatedOn(\DateTimeInterface $date): static
    {
        return $this->state(fn (array $attributes) => [
            'graduation_date' => $date,
            'is_current' => false,
            'is_active' => false,
        ]);
    }
}
