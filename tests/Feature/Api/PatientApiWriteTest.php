<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Core\Models\Branch;
use Modules\Patient\Models\Patient;
use Tests\Support\ApiResourceTestSuite;
use Tests\TestCase;

uses(TestCase::class, DatabaseTransactions::class);

ApiResourceTestSuite::register([
    'uri' => 'patients',
    'subject' => 'Patient',
    'scope' => 'patients',
    'modules' => ['Core', 'Patient'],
    'make' => fn (TestCase $test, Branch $branch, array $attributes = []) => Patient::withoutEvents(fn () => Patient::factory()->create(['branch_id' => $branch->id, ...$attributes])),
    'store' => fn (TestCase $test, Branch $branch): array => ['first_name' => 'Akosua', 'last_name' => 'Boateng', 'gender' => 'female', 'date_of_birth' => '1990-04-12'],
    'invalid' => [['first_name' => 'Akosua'], 'last_name'],
    'update' => fn (TestCase $test, $record): array => ['occupation' => 'Teacher'],
    'destroy' => true,
]);
