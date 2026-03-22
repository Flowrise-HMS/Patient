<?php

namespace Modules\Patient\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class PatientIdentifierFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = \Modules\Patient\Models\PatientIdentifier::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [];
    }
}

