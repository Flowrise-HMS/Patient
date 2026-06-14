<?php

namespace Modules\Patient\Tests\Unit\DataTransferObjects;

use Modules\Patient\DataTransferObjects\PatientData;
use Tests\TestCase;

class PatientDataTest extends TestCase
{
    public function test_from_array_creates_dto(): void
    {
        $data = PatientData::fromArray([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'global_uuid' => 'uuid-123',
            'gender' => 'male',
            'date_of_birth' => '1990-01-15',
            'phone' => '233123456789',
        ]);

        $this->assertSame('John', $data->firstName);
        $this->assertSame('Doe', $data->lastName);
        $this->assertSame('uuid-123', $data->globalUuid);
        $this->assertSame('male', $data->gender);
    }

    public function test_from_array_with_required_only(): void
    {
        $data = PatientData::fromArray([
            'first_name' => 'Jane',
            'last_name' => 'Smith',
        ]);

        $this->assertSame('Jane', $data->firstName);
        $this->assertSame('Smith', $data->lastName);
        $this->assertNull($data->gender);
        $this->assertNull($data->dateOfBirth);
        $this->assertNull($data->phone);
    }

    public function test_to_array_round_trip(): void
    {
        $original = [
            'first_name' => 'Alice',
            'last_name' => 'Wonderland',
            'gender' => 'female',
            'is_active' => true,
        ];

        $result = PatientData::fromArray($original)->toArray();

        $this->assertSame($original['first_name'], $result['first_name']);
        $this->assertSame($original['last_name'], $result['last_name']);
        $this->assertSame($original['gender'], $result['gender']);
        $this->assertTrue($result['is_active']);
    }

    public function test_to_array_omits_nulls(): void
    {
        $result = PatientData::fromArray([
            'first_name' => 'Bob',
            'last_name' => 'Builder',
        ])->toArray();

        $this->assertArrayNotHasKey('global_uuid', $result);
        $this->assertArrayNotHasKey('gender', $result);
        $this->assertArrayNotHasKey('phone', $result);
        $this->assertArrayNotHasKey('email', $result);
        $this->assertArrayNotHasKey('address', $result);
    }
}
