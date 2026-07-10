<?php

use Tests\TestCase;

uses(TestCase::class);

use Illuminate\Support\Collection;
use Modules\FHIR\Contracts\FhirResourceContract;
use Modules\Patient\Classes\Fhir\FhirPatientTransformer;
use Modules\Patient\Enums\Gender;
use Modules\Patient\Enums\MaritalStatus;
use Modules\Patient\Models\EmergencyContact;
use Modules\Patient\Models\Patient;
use Modules\Patient\Models\PatientIdentifier;

$transformer = new FhirPatientTransformer;

test('implements FhirResourceContract', function () use ($transformer) {
    expect($transformer)->toBeInstanceOf(FhirResourceContract::class);
});

test('resourceType returns Patient', function () use ($transformer) {
    expect($transformer->resourceType())->toBe('Patient');
});

test('toFhir contains required fields', function () use ($transformer) {
    $patient = (new class extends Patient
    {
        public $timestamps = false;
    });
    $patient->setAttribute('id', 'pat-0001');
    $patient->setAttribute('mrn', 'MRN-001');
    $patient->setAttribute('first_name', 'John');
    $patient->setAttribute('middle_name', 'M');
    $patient->setAttribute('last_name', 'Doe');
    $patient->setAttribute('title', 'Mr');
    $patient->setAttribute('gender', Gender::MALE);
    $patient->setAttribute('date_of_birth', '1990-01-15');
    $patient->setAttribute('is_active', true);
    $patient->setAttribute('is_deceased', false);

    $fhir = $transformer->toFhir($patient);

    expect($fhir)->toHaveKey('resourceType', 'Patient');
    expect($fhir)->toHaveKey('id', 'pat-0001');
    expect($fhir['identifier'][0]['value'])->toBe('MRN-001');
    expect($fhir)->toHaveKey('active', true);
    expect($fhir['name'][0]['use'])->toBe('official');
    expect($fhir['name'][0]['family'])->toBe('Doe');
    expect($fhir['name'][0]['given'])->toBe(['John', 'M']);
    expect($fhir['name'][0]['prefix'])->toBe(['Mr']);
    expect($fhir['gender'])->toBe('male');
    expect($fhir['birthDate'])->toBe('1990-01-15');
    expect($fhir['deceasedBoolean'])->toBeFalse();
});

test('toFhir maps deceased correctly', function () use ($transformer) {
    $patient = (new class extends Patient
    {
        public $timestamps = false;
    });
    $patient->setAttribute('id', 'pat-dec-1');
    $patient->setAttribute('mrn', 'MRN-DEC1');
    $patient->setAttribute('last_name', 'Dec');
    $patient->setAttribute('gender', Gender::MALE);
    $patient->setAttribute('date_of_birth', '1950-01-01');
    $patient->setAttribute('is_active', false);
    $patient->setAttribute('is_deceased', true);

    $fhir = $transformer->toFhir($patient);

    expect($fhir['deceasedBoolean'])->toBeTrue();
    expect($fhir)->not->toHaveKey('deceasedDateTime');

    $now = now();
    $patient2 = (new class extends Patient
    {
        public $timestamps = false;
    });
    $patient2->setAttribute('id', 'pat-dec-2');
    $patient2->setAttribute('mrn', 'MRN-DEC2');
    $patient2->setAttribute('last_name', 'Dec2');
    $patient2->setAttribute('gender', Gender::FEMALE);
    $patient2->setAttribute('date_of_birth', '1950-06-01');
    $patient2->setAttribute('is_active', false);
    $patient2->setAttribute('is_deceased', true);
    $patient2->setAttribute('deceased_at', $now);

    $fhir2 = $transformer->toFhir($patient2);

    expect($fhir2['deceasedBoolean'])->toBeTrue();
    expect($fhir2['deceasedDateTime'])->toBe($now->toIso8601String());
});

test('toFhir maps address', function () use ($transformer) {
    $patient = (new class extends Patient
    {
        public $timestamps = false;
    });
    $patient->setAttribute('id', 'pat-addr-1');
    $patient->setAttribute('mrn', 'MRN-ADDR1');
    $patient->setAttribute('last_name', 'Addr');
    $patient->setAttribute('gender', Gender::MALE);
    $patient->setAttribute('date_of_birth', '1985-01-01');
    $patient->setAttribute('is_active', true);
    $patient->setAttribute('is_deceased', false);
    $patient->setAttribute('address', [
        'street' => '123 Main St',
        'city' => 'Springfield',
        'district' => 'Central',
        'region' => 'IL',
        'postal_code' => '62701',
        'country' => 'USA',
    ]);

    $fhir = $transformer->toFhir($patient);

    expect($fhir['address'][0]['use'])->toBe('home');
    expect($fhir['address'][0]['line'])->toBe(['123 Main St']);
    expect($fhir['address'][0]['city'])->toBe('Springfield');
    expect($fhir['address'][0]['district'])->toBe('Central');
    expect($fhir['address'][0]['state'])->toBe('IL');
    expect($fhir['address'][0]['postalCode'])->toBe('62701');
    expect($fhir['address'][0]['country'])->toBe('USA');
});

test('toFhir maps maritalStatus', function () use ($transformer) {
    $patient = (new class extends Patient
    {
        public $timestamps = false;
    });
    $patient->setAttribute('id', 'pat-ms-1');
    $patient->setAttribute('mrn', 'MRN-MS1');
    $patient->setAttribute('last_name', 'Ms');
    $patient->setAttribute('gender', Gender::FEMALE);
    $patient->setAttribute('date_of_birth', '1990-01-01');
    $patient->setAttribute('is_active', true);
    $patient->setAttribute('is_deceased', false);
    $patient->setAttribute('marital_status', MaritalStatus::MARRIED);

    $fhir = $transformer->toFhir($patient);

    expect($fhir['maritalStatus']['coding'][0]['system'])->toBe('http://terminology.hl7.org/CodeSystem/v3-MaritalStatus');
    expect($fhir['maritalStatus']['coding'][0]['code'])->toBe('M');
});

test('toFhir maps managingOrganization', function () use ($transformer) {
    $patient = (new class extends Patient
    {
        public $timestamps = false;
    });
    $patient->setAttribute('id', 'pat-org-1');
    $patient->setAttribute('mrn', 'MRN-ORG1');
    $patient->setAttribute('last_name', 'Org');
    $patient->setAttribute('gender', Gender::MALE);
    $patient->setAttribute('date_of_birth', '1990-01-01');
    $patient->setAttribute('is_active', true);
    $patient->setAttribute('is_deceased', false);
    $patient->setAttribute('branch_id', 'branch-uuid');

    $fhir = $transformer->toFhir($patient);

    expect($fhir['managingOrganization']['reference'])->toBe('Organization/branch-uuid');
});

test('toFhir maps emergency contacts', function () use ($transformer) {
    $contact = (new class extends EmergencyContact
    {
        public $timestamps = false;
    });
    $contact->setAttribute('name', 'Jane Doe');
    $contact->setAttribute('relationship', 'spouse');

    $patient = (new class extends Patient
    {
        public $timestamps = false;
    });
    $patient->setAttribute('id', 'pat-ec-1');
    $patient->setAttribute('mrn', 'MRN-EC1');
    $patient->setAttribute('last_name', 'Ec');
    $patient->setAttribute('gender', Gender::MALE);
    $patient->setAttribute('date_of_birth', '1990-01-01');
    $patient->setAttribute('is_active', true);
    $patient->setAttribute('is_deceased', false);
    $patient->setRelation('emergencyContacts', new Collection([$contact]));

    $fhir = $transformer->toFhir($patient);

    expect($fhir['contact'][0]['relationship'][0]['coding'][0]['code'])->toBe('C');
    expect($fhir['contact'][0]['name']['family'])->toBe('Jane Doe');
});

test('toFhir builds additional identifiers from relation', function () use ($transformer) {
    $identifier = (new class extends PatientIdentifier
    {
        public $timestamps = false;
    });
    $identifier->setAttribute('type', 'nhis');
    $identifier->setAttribute('value', 'NHIS-12345');
    $identifier->setAttribute('issuer', 'NHIS');
    $identifier->setAttribute('issue_date', '2026-01-01');
    $identifier->setAttribute('expiry_date', '2026-12-31');

    $patient = (new class extends Patient
    {
        public $timestamps = false;
    });
    $patient->setAttribute('id', 'pat-id-1');
    $patient->setAttribute('mrn', 'MRN-ID1');
    $patient->setAttribute('last_name', 'Id');
    $patient->setAttribute('gender', Gender::FEMALE);
    $patient->setAttribute('date_of_birth', '1990-01-01');
    $patient->setAttribute('is_active', true);
    $patient->setAttribute('is_deceased', false);
    $patient->setRelation('identifiers', new Collection([$identifier]));

    $fhir = $transformer->toFhir($patient);

    expect($fhir['identifier'][0]['value'])->toBe('MRN-ID1');
    expect($fhir['identifier'][1]['type']['coding'][0]['code'])->toBe('SS');
    expect($fhir['identifier'][1]['value'])->toBe('NHIS-12345');
    expect($fhir['identifier'][1]['assigner']['display'])->toBe('NHIS');
    expect($fhir['identifier'][1]['period']['start'])->toBe('2026-01-01');
    expect($fhir['identifier'][1]['period']['end'])->toBe('2026-12-31');
});

test('toFhir omits optional fields when not applicable', function () use ($transformer) {
    $patient = (new class extends Patient
    {
        public $timestamps = false;
    });
    $patient->setAttribute('id', 'pat-min-1');
    $patient->setAttribute('mrn', 'MRN-MIN1');
    $patient->setAttribute('last_name', 'Min');
    $patient->setAttribute('gender', Gender::MALE);
    $patient->setAttribute('date_of_birth', '1990-01-01');
    $patient->setAttribute('is_active', true);
    $patient->setAttribute('is_deceased', false);

    $fhir = $transformer->toFhir($patient);

    expect($fhir)->not->toHaveKey('address');
    expect($fhir)->not->toHaveKey('maritalStatus');
    expect($fhir)->not->toHaveKey('contact');
    expect($fhir)->not->toHaveKey('managingOrganization');
});

test('fromFhir extracts attributes correctly', function () use ($transformer) {
    $fhirResource = [
        'resourceType' => 'Patient',
        'name' => [
            [
                'use' => 'official',
                'family' => 'Doe',
                'given' => ['John', 'M'],
            ],
        ],
        'gender' => 'male',
        'birthDate' => '1990-01-15',
        'identifier' => [
            [
                'type' => [
                    'coding' => [
                        ['system' => 'http://terminology.hl7.org/CodeSystem/v2-0203', 'code' => 'MR'],
                    ],
                ],
                'value' => 'MRN-001',
            ],
        ],
        'contact' => [
            [
                'relationship' => [
                    [
                        'coding' => [
                            ['system' => 'http://terminology.hl7.org/CodeSystem/v2-0131', 'code' => 'C'],
                        ],
                    ],
                ],
                'name' => ['family' => 'Doe', 'given' => ['Jane']],
            ],
        ],
    ];

    $attrs = $transformer->fromFhir($fhirResource);

    expect($attrs)->toHaveKey('first_name', 'John');
    expect($attrs)->toHaveKey('middle_name', 'M');
    expect($attrs)->toHaveKey('last_name', 'Doe');
    expect($attrs)->toHaveKey('gender', 'male');
    expect($attrs)->toHaveKey('birth_date', '1990-01-15');
    expect($attrs)->toHaveKey('_identifiers');
    expect($attrs)->toHaveKey('_emergencyContacts');
});

test('fromFhir handles minimal resource', function () use ($transformer) {
    $fhirResource = [
        'resourceType' => 'Patient',
        'gender' => 'female',
    ];

    $attrs = $transformer->fromFhir($fhirResource);

    expect($attrs)->toHaveKey('gender', 'female');
    expect($attrs)->not->toHaveKey('_identifiers');
    expect($attrs)->not->toHaveKey('_emergencyContacts');
});

test('searchableParameters has expected keys', function () use ($transformer) {
    $params = $transformer->searchableParameters();

    expect($params)->toHaveKeys(['_id', 'name', 'family', 'given', 'birthdate', 'gender', 'identifier', 'mrn', 'active', 'phone', 'email']);
    expect($params['_id'])->toHaveKey('column', 'id');
    expect($params['family'])->toHaveKey('column', 'last_name');
    expect($params['mrn'])->toHaveKey('column', 'mrn');
});

test('validateBusinessRules passes with valid data', function () use ($transformer) {
    $resource = ['resourceType' => 'Patient', 'name' => [['use' => 'official', 'family' => 'Doe']], 'gender' => 'male'];

    $errors = $transformer->validateBusinessRules($resource);

    expect($errors)->toBeEmpty();
});

test('validateBusinessRules fails without family name', function () use ($transformer) {
    $resource = ['resourceType' => 'Patient', 'name' => [['use' => 'official', 'given' => ['John']]]];

    $errors = $transformer->validateBusinessRules($resource);

    expect($errors)->toHaveKey('family');
});

test('validateBusinessRules fails with invalid gender', function () use ($transformer) {
    $resource = ['resourceType' => 'Patient', 'name' => [['use' => 'official', 'family' => 'Doe']], 'gender' => 'alien'];

    $errors = $transformer->validateBusinessRules($resource);

    expect($errors)->toHaveKey('gender');
});
