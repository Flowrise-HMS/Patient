<?php

namespace Modules\Patient\Tests\Unit;

use Carbon\Carbon;
use Modules\Core\Enums\Title;
use Modules\FHIR\Contracts\FhirResourceContract;
use Modules\Patient\Classes\Fhir\FhirPatientTransformer;
use Modules\Patient\Enums\BloodType;
use Modules\Patient\Enums\Gender;
use Modules\Patient\Enums\IdentifierType;
use Modules\Patient\Enums\MaritalStatus;
use Modules\Patient\Enums\RelationshipType;
use Modules\Patient\Models\EmergencyContact;
use Modules\Patient\Models\Patient;
use Modules\Patient\Models\PatientIdentifier;
use Tests\TestCase;

class FhirPatientTransformerTest extends TestCase
{
    private function createTransformer(): FhirResourceContract
    {
        return new FhirPatientTransformer;
    }

    private function createMinimalPatient(): Patient
    {
        $patient = new Patient;
        $patient->id = '550e8400-e29b-41d4-a716-446655440000';
        $patient->mrn = 'MRN-001';
        $patient->first_name = 'John';
        $patient->last_name = 'Smith';
        $patient->gender = Gender::MALE;
        $patient->date_of_birth = Carbon::parse('1990-01-15');
        $patient->is_active = true;
        $patient->is_deceased = false;
        $patient->address = [
            'street' => '123 Main St',
            'city' => 'Accra',
            'district' => 'Korle-Klottey',
            'region' => 'Greater Accra',
            'postal_code' => '00233',
            'country' => 'GH',
        ];

        return $patient;
    }

    public function test_implements_contract(): void
    {
        $transformer = $this->createTransformer();
        $this->assertInstanceOf(FhirResourceContract::class, $transformer);
    }

    public function test_resource_type_returns_patient(): void
    {
        $transformer = $this->createTransformer();

        $this->assertEquals('Patient', $transformer->resourceType());
    }

    public function test_to_fhir_contains_required_fields(): void
    {
        $patient = $this->createMinimalPatient();
        $transformer = $this->createTransformer();

        $fhir = $transformer->toFhir($patient);

        $this->assertArrayHasKey('resourceType', $fhir);
        $this->assertArrayHasKey('id', $fhir);
        $this->assertArrayHasKey('name', $fhir);
        $this->assertArrayHasKey('gender', $fhir);
        $this->assertArrayHasKey('birthDate', $fhir);
        $this->assertEquals('Patient', $fhir['resourceType']);
        $this->assertEquals($patient->id, $fhir['id']);
        $this->assertEquals('John', $fhir['name'][0]['given'][0]);
        $this->assertEquals('Smith', $fhir['name'][0]['family']);
    }

    public function test_to_fhir_includes_mrn_identifier(): void
    {
        $patient = $this->createMinimalPatient();
        $transformer = $this->createTransformer();

        $fhir = $transformer->toFhir($patient);

        $this->assertArrayHasKey('identifier', $fhir);
        $mrnIdentifiers = array_filter($fhir['identifier'], fn ($i) => $i['use'] === 'usual');
        $this->assertCount(1, $mrnIdentifiers);
        $mrnIdentifier = reset($mrnIdentifiers);
        $this->assertEquals('MRN-001', $mrnIdentifier['value']);
        $this->assertEquals('MR', $mrnIdentifier['type']['coding'][0]['code']);
    }

    public function test_to_fhir_includes_patient_identifiers(): void
    {
        $patient = $this->createMinimalPatient();

        $nationalId = new PatientIdentifier;
        $nationalId->type = IdentifierType::NATIONAL_ID;
        $nationalId->value = 'GHA-123456';
        $nationalId->issuer = 'NIA';

        $patient->setRelation('identifiers', collect([$nationalId]));
        $transformer = $this->createTransformer();

        $fhir = $transformer->toFhir($patient);

        $fhirIdentifiers = $fhir['identifier'];
        $fhirNationalId = array_filter($fhirIdentifiers, fn ($i) => $i['use'] === 'official');
        $this->assertCount(1, $fhirNationalId);
        $nationalIdEntry = reset($fhirNationalId);
        $this->assertEquals('GHA-123456', $nationalIdEntry['value']);
        $this->assertEquals('National ID', $nationalIdEntry['type']['text']);
        $this->assertEquals('NIA', $nationalIdEntry['assigner']['display']);
    }

    public function test_to_fhir_includes_emergency_contacts(): void
    {
        $patient = $this->createMinimalPatient();

        $contact = new EmergencyContact;
        $contact->name = 'Jane Smith';
        $contact->relationship = RelationshipType::SPOUSE;

        $patient->setRelation('emergencyContacts', collect([$contact]));
        $transformer = $this->createTransformer();

        $fhir = $transformer->toFhir($patient);

        $this->assertArrayHasKey('contact', $fhir);
        $this->assertCount(1, $fhir['contact']);
        $this->assertEquals('Jane Smith', $fhir['contact'][0]['name']['family']);
        $this->assertEquals('C', $fhir['contact'][0]['relationship'][0]['coding'][0]['code']);
    }

    public function test_to_fhir_maps_active(): void
    {
        $patient = $this->createMinimalPatient();
        $patient->is_active = true;
        $transformer = $this->createTransformer();

        $fhir = $transformer->toFhir($patient);

        $this->assertTrue($fhir['active']);
    }

    public function test_to_fhir_maps_is_deceased(): void
    {
        $patient = $this->createMinimalPatient();
        $patient->is_deceased = true;
        $patient->deceased_at = Carbon::parse('2024-03-01 10:00:00');
        $transformer = $this->createTransformer();

        $fhir = $transformer->toFhir($patient);

        $this->assertTrue($fhir['deceasedBoolean']);
        $this->assertArrayHasKey('deceasedDateTime', $fhir);
    }

    public function test_to_fhir_maps_non_deceased(): void
    {
        $patient = $this->createMinimalPatient();
        $patient->is_deceased = false;
        $patient->deceased_at = null;
        $transformer = $this->createTransformer();

        $fhir = $transformer->toFhir($patient);

        $this->assertFalse($fhir['deceasedBoolean']);
        $this->assertArrayNotHasKey('deceasedDateTime', $fhir);
    }

    public function test_to_fhir_includes_managing_organization(): void
    {
        $patient = $this->createMinimalPatient();
        $patient->branch_id = 'branch-uuid-123';
        $transformer = $this->createTransformer();

        $fhir = $transformer->toFhir($patient);

        $this->assertArrayHasKey('managingOrganization', $fhir);
        $this->assertEquals('Organization/branch-uuid-123', $fhir['managingOrganization']['reference']);
    }

    public function test_to_fhir_maps_marital_status(): void
    {
        $patient = $this->createMinimalPatient();
        $patient->marital_status = MaritalStatus::MARRIED;
        $transformer = $this->createTransformer();

        $fhir = $transformer->toFhir($patient);

        $this->assertArrayHasKey('maritalStatus', $fhir);
        $this->assertEquals('M', $fhir['maritalStatus']['coding'][0]['code']);
    }

    public function test_to_fhir_maps_name_with_title(): void
    {
        $patient = $this->createMinimalPatient();
        $patient->title = Title::MR;
        $patient->middle_name = 'Michael';
        $transformer = $this->createTransformer();

        $fhir = $transformer->toFhir($patient);

        $name = $fhir['name'][0];
        $this->assertEquals('official', $name['use']);
        $this->assertEquals('Smith', $name['family']);
        $this->assertEquals(['John', 'Michael'], $name['given']);
        $this->assertEquals(['Mr'], $name['prefix']);
    }

    public function test_from_fhir_returns_correct_structure(): void
    {
        $transformer = $this->createTransformer();

        $fhirResource = [
            'resourceType' => 'Patient',
            'id' => 'ext-uuid',
            'name' => [
                [
                    'use' => 'official',
                    'family' => 'Smith',
                    'given' => ['John', 'Michael'],
                    'prefix' => ['Mr'],
                ],
            ],
            'gender' => 'male',
            'birthDate' => '1990-01-15',
            'identifier' => [
                [
                    'use' => 'official',
                    'type' => [
                        'coding' => [['system' => 'http://terminology.hl7.org/CodeSystem/v2-0203', 'code' => 'NNxxx']],
                        'text' => 'National ID',
                    ],
                    'value' => 'GHA-123456',
                    'assigner' => ['display' => 'NIA'],
                ],
            ],
            'contact' => [
                [
                    'relationship' => [
                        ['coding' => [['system' => 'http://terminology.hl7.org/CodeSystem/v2-0131', 'code' => 'C']], 'text' => 'Spouse'],
                    ],
                    'name' => ['family' => 'Smith', 'given' => ['Jane']],
                ],
            ],
        ];

        $result = $transformer->fromFhir($fhirResource);

        $this->assertArrayHasKey('first_name', $result);
        $this->assertArrayHasKey('last_name', $result);
        $this->assertArrayHasKey('gender', $result);
        $this->assertArrayHasKey('birth_date', $result);
        $this->assertArrayHasKey('_identifiers', $result);
        $this->assertArrayHasKey('_emergencyContacts', $result);
        $this->assertEquals('John', $result['first_name']);
        $this->assertEquals('Smith', $result['last_name']);
        $this->assertEquals('male', $result['gender']);
        $this->assertEquals('1990-01-15', $result['birth_date']);
    }

    public function test_from_fhir_maps_identifiers(): void
    {
        $transformer = $this->createTransformer();

        $fhirResource = [
            'resourceType' => 'Patient',
            'name' => [['family' => 'Doe', 'given' => ['Jane']]],
            'gender' => 'female',
            'identifier' => [
                [
                    'use' => 'official',
                    'type' => [
                        'coding' => [['system' => 'http://terminology.hl7.org/CodeSystem/v2-0203', 'code' => 'NNxxx']],
                        'text' => 'National ID',
                    ],
                    'value' => 'GHA-123456',
                    'assigner' => ['display' => 'NIA'],
                ],
                [
                    'use' => 'official',
                    'type' => [
                        'coding' => [['system' => 'http://terminology.hl7.org/CodeSystem/v2-0203', 'code' => 'SS']],
                        'text' => 'NHIS',
                    ],
                    'value' => 'NHIS-98765',
                    'assigner' => ['display' => 'NHIA'],
                ],
            ],
        ];

        $result = $transformer->fromFhir($fhirResource);

        $this->assertCount(2, $result['_identifiers']);
        $this->assertEquals('national_id', $result['_identifiers'][0]['type']);
        $this->assertEquals('GHA-123456', $result['_identifiers'][0]['value']);
        $this->assertEquals('NIA', $result['_identifiers'][0]['issuer']);
        $this->assertEquals('nhis', $result['_identifiers'][1]['type']);
        $this->assertEquals('NHIS-98765', $result['_identifiers'][1]['value']);
    }

    public function test_from_fhir_maps_emergency_contacts(): void
    {
        $transformer = $this->createTransformer();

        $fhirResource = [
            'resourceType' => 'Patient',
            'name' => [['family' => 'Doe', 'given' => ['Jane']]],
            'gender' => 'female',
            'contact' => [
                [
                    'relationship' => [
                        ['coding' => [['system' => 'http://terminology.hl7.org/CodeSystem/v2-0131', 'code' => 'C']], 'text' => 'Spouse'],
                    ],
                    'name' => ['family' => 'Smith', 'given' => ['John']],
                ],
            ],
        ];

        $result = $transformer->fromFhir($fhirResource);

        $this->assertCount(1, $result['_emergencyContacts']);
        $this->assertEquals('John Smith', $result['_emergencyContacts'][0]['name']);
        $this->assertEquals('spouse', $result['_emergencyContacts'][0]['relationship']);
    }

    public function test_searchable_parameters_has_identifier_with_relation(): void
    {
        $transformer = $this->createTransformer();

        $params = $transformer->searchableParameters();

        $this->assertArrayHasKey('identifier', $params);
        $this->assertArrayHasKey('relation', $params['identifier']);
        $this->assertEquals('identifiers', $params['identifier']['relation']);
        $this->assertArrayHasKey('_id', $params);
        $this->assertArrayHasKey('name', $params);
        $this->assertArrayHasKey('family', $params);
        $this->assertArrayHasKey('given', $params);
        $this->assertArrayHasKey('birthdate', $params);
        $this->assertArrayHasKey('gender', $params);
        $this->assertArrayHasKey('mrn', $params);
        $this->assertArrayHasKey('active', $params);
        $this->assertArrayHasKey('phone', $params);
        $this->assertArrayHasKey('email', $params);
    }

    public function test_validate_business_rules_checks_family_name(): void
    {
        $transformer = $this->createTransformer();

        $resource = [
            'resourceType' => 'Patient',
            'name' => [
                ['given' => ['John']],
            ],
        ];

        $errors = $transformer->validateBusinessRules($resource);

        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('family', $errors);
    }

    public function test_validate_business_rules_passes_valid_resource(): void
    {
        $transformer = $this->createTransformer();

        $resource = [
            'resourceType' => 'Patient',
            'name' => [
                ['family' => 'Smith', 'given' => ['John']],
            ],
            'gender' => 'male',
        ];

        $errors = $transformer->validateBusinessRules($resource);

        $this->assertEmpty($errors);
    }
}
