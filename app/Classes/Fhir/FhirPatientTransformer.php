<?php

namespace Modules\Patient\Classes\Fhir;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\FHIR\Contracts\FhirResourceContract;
use Modules\Patient\Enums\IdentifierType;
use Modules\Patient\Enums\MaritalStatus;
use Modules\Patient\Enums\RelationshipType;
use Modules\Patient\Models\Patient;

class FhirPatientTransformer implements FhirResourceContract
{
    protected static array $identifierSystemMap = [
        'mrn' => ['system' => 'http://hl7.org/fhir/sid/us-ssn', 'code' => 'MR'],
        'nhis' => ['system' => 'http://terminology.hl7.org/CodeSystem/v2-0203', 'code' => 'SS'],
        'national_id' => ['system' => 'http://terminology.hl7.org/CodeSystem/v2-0203', 'code' => 'NNxxx'],
        'passport' => ['system' => 'http://terminology.hl7.org/CodeSystem/v2-0203', 'code' => 'PPN'],
        'driver_license' => ['system' => 'http://terminology.hl7.org/CodeSystem/v2-0203', 'code' => 'DL'],
        'birth_certificate' => ['system' => 'http://terminology.hl7.org/CodeSystem/v2-0203', 'code' => 'BC'],
        'ssnit' => ['system' => 'http://terminology.hl7.org/CodeSystem/v2-0203', 'code' => 'SSN'],
        'voter_id' => ['system' => 'http://terminology.hl7.org/CodeSystem/v2-0203', 'code' => 'VNI'],
        'alien_id' => ['system' => 'http://terminology.hl7.org/CodeSystem/v2-0203', 'code' => 'AN'],
        'other' => ['system' => 'https://flowrise.app/identifier-type/other', 'code' => 'OTH'],
    ];

    protected static array $identifierTextMap = [
        'mrn' => 'MRN',
        'nhis' => 'NHIS',
        'national_id' => 'National ID',
        'passport' => 'Passport',
        'driver_license' => "Driver's License",
        'birth_certificate' => 'Birth Certificate',
        'ssnit' => 'SSNIT',
        'voter_id' => "Voter's ID",
        'alien_id' => 'Alien ID',
        'other' => 'Other',
    ];

    protected static array $relationshipCodeMap = [
        'spouse' => 'C',
        'parent' => 'PRN',
        'child' => 'CHILD',
        'sibling' => 'SIB',
        'grandparent' => 'GRPRN',
        'grandchild' => 'GRCHILD',
        'uncle' => 'UNCLE',
        'friend' => 'FRIEND',
        'neighbor' => 'NEIGHBOR',
        'colleague' => 'COLLEAGUE',
        'guardian' => 'GUARD',
        'caregiver' => 'CAREGIVER',
        'other' => 'OTH',
    ];

    private static ?array $codeToIdentifierType = null;

    private static ?array $codeToRelationship = null;

    private static function getCodeToIdentifierType(): array
    {
        if (self::$codeToIdentifierType === null) {
            self::$codeToIdentifierType = [];
            foreach (self::$identifierSystemMap as $type => $map) {
                self::$codeToIdentifierType[$map['code']] = $type;
            }
        }

        return self::$codeToIdentifierType;
    }

    private static function getCodeToRelationship(): array
    {
        if (self::$codeToRelationship === null) {
            self::$codeToRelationship = [];
            foreach (self::$relationshipCodeMap as $type => $code) {
                self::$codeToRelationship[$code] = $type;
            }
        }

        return self::$codeToRelationship;
    }

    protected static array $maritalStatusFhirMap = [
        'single' => 'S',
        'married' => 'M',
        'divorced' => 'D',
        'widowed' => 'W',
        'separated' => 'L',
        'cohabiting' => 'T',
        'unknown' => 'U',
    ];

    public function resourceType(): string
    {
        return 'Patient';
    }

    public function toFhir(Model $model): array
    {
        $patient = $model;

        $identifiers = $this->buildIdentifiers($patient);
        $name = $this->getNameEntry($patient);
        $addresses = $this->getAddressEntries($patient);

        $fhir = [
            'resourceType' => 'Patient',
            'id' => $patient->id,
            'identifier' => $identifiers,
            'active' => (bool) $patient->is_active,
            'name' => [$name],
            'gender' => $patient->gender?->value,
            'birthDate' => $patient->date_of_birth?->format('Y-m-d'),
        ];

        if ($patient->is_deceased) {
            $fhir['deceasedBoolean'] = true;
            if ($patient->deceased_at) {
                $fhir['deceasedDateTime'] = $patient->deceased_at->toIso8601String();
            }
        } else {
            $fhir['deceasedBoolean'] = false;
        }

        if (! empty($addresses)) {
            $fhir['address'] = $addresses;
        }

        if ($patient->marital_status) {
            $fhir['maritalStatus'] = $this->getMaritalStatusEntry($patient->marital_status);
        }

        $contacts = $this->getContactEntries($patient);
        if (! empty($contacts)) {
            $fhir['contact'] = $contacts;
        }

        if ($patient->branch_id) {
            $fhir['managingOrganization'] = [
                'reference' => "Organization/{$patient->branch_id}",
            ];
        }

        return $fhir;
    }

    public function fromFhir(array $fhirResource): array
    {
        $result = [];

        $name = $this->extractOfficialName($fhirResource['name'] ?? []);
        if ($name) {
            $given = $name['given'] ?? [];
            $result['first_name'] = $given[0] ?? '';
            $result['middle_name'] = $given[1] ?? null;
            $result['last_name'] = $name['family'] ?? '';
        }

        $result['gender'] = $fhirResource['gender'] ?? null;
        $result['birth_date'] = $fhirResource['birthDate'] ?? null;

        $identifiers = $this->extractIdentifiers($fhirResource['identifier'] ?? []);
        if (! empty($identifiers)) {
            $result['_identifiers'] = $identifiers;
        }

        $contacts = $this->extractEmergencyContacts($fhirResource['contact'] ?? []);
        if (! empty($contacts)) {
            $result['_emergencyContacts'] = $contacts;
        }

        return $result;
    }

    public function findById(string $id): ?Model
    {
        return Patient::withTrashed()->find($id);
    }

    public function query(): Builder
    {
        return Patient::query();
    }

    public function searchableParameters(): array
    {
        return [
            '_id' => ['column' => 'id'],
            'name' => ['column' => 'last_name'],
            'family' => ['column' => 'last_name'],
            'given' => ['column' => 'first_name'],
            'birthdate' => ['column' => 'date_of_birth'],
            'gender' => ['column' => 'gender'],
            'identifier' => ['relation' => 'identifiers', 'column' => 'value'],
            'mrn' => ['column' => 'mrn'],
            'active' => ['column' => 'is_active'],
            'phone' => ['relation' => 'identifiers'],
            'email' => ['relation' => 'identifiers'],
        ];
    }

    public function validateBusinessRules(array $fhirResource): array
    {
        $errors = [];

        $name = $this->extractOfficialName($fhirResource['name'] ?? []);
        if (! $name || empty($name['family'])) {
            $errors['family'] = 'Family name (last_name) is required.';
        }

        $gender = $fhirResource['gender'] ?? null;
        if ($gender !== null && ! in_array($gender, ['male', 'female', 'other', 'unknown'], true)) {
            $errors['gender'] = 'Gender must be a valid FHIR value (male, female, other, unknown).';
        }

        return $errors;
    }

    private function buildIdentifiers(Patient $patient): array
    {
        $identifiers = [];

        $identifiers[] = [
            'use' => 'usual',
            'type' => [
                'coding' => [
                    [
                        'system' => 'http://terminology.hl7.org/CodeSystem/v2-0203',
                        'code' => 'MR',
                    ],
                ],
                'text' => 'MRN',
            ],
            'value' => $patient->mrn,
        ];

        $patientIdentifiers = $patient->relationLoaded('identifiers') ? $patient->identifiers : null;
        if ($patientIdentifiers) {
            foreach ($patientIdentifiers as $pi) {
                $identifierType = $pi->type instanceof IdentifierType ? $pi->type->value : $pi->type;
                $mapping = self::$identifierSystemMap[$identifierType] ?? self::$identifierSystemMap['other'];
                $label = self::$identifierTextMap[$identifierType] ?? $identifierType;

                $entry = [
                    'use' => 'official',
                    'type' => [
                        'coding' => [
                            [
                                'system' => $mapping['system'],
                                'code' => $mapping['code'],
                            ],
                        ],
                        'text' => $label,
                    ],
                    'value' => $pi->value,
                ];

                if ($pi->issuer) {
                    $entry['assigner'] = ['display' => $pi->issuer];
                }

                if ($pi->issue_date) {
                    $entry['period'] = [
                        'start' => $pi->issue_date->format('Y-m-d'),
                    ];
                }

                if ($pi->expiry_date) {
                    if (! isset($entry['period'])) {
                        $entry['period'] = [];
                    }
                    $entry['period']['end'] = $pi->expiry_date->format('Y-m-d');
                }

                $identifiers[] = $entry;
            }
        }

        return $identifiers;
    }

    private function getNameEntry(Patient $patient): array
    {
        $entry = [
            'use' => 'official',
            'family' => $patient->last_name,
        ];

        $given = array_filter([$patient->first_name, $patient->middle_name]);
        if (! empty($given)) {
            $entry['given'] = array_values($given);
        }

        if ($patient->title) {
            $entry['prefix'] = [$patient->title instanceof \BackedEnum ? $patient->title->value : $patient->title];
        }

        return $entry;
    }

    private function getAddressEntries(Patient $patient): array
    {
        if (! $patient->address) {
            return [];
        }

        $address = is_string($patient->address) ? json_decode($patient->address, true) : $patient->address;
        if (empty($address)) {
            return [];
        }

        $entry = ['use' => 'home'];

        if (! empty($address['street'])) {
            $entry['line'] = [$address['street']];
        }

        if (! empty($address['city'])) {
            $entry['city'] = $address['city'];
        }

        if (! empty($address['district'])) {
            $entry['district'] = $address['district'];
        }

        if (! empty($address['region'])) {
            $entry['state'] = $address['region'];
        }

        if (! empty($address['postal_code'])) {
            $entry['postalCode'] = $address['postal_code'];
        }

        if (! empty($address['country'])) {
            $entry['country'] = $address['country'];
        }

        return [$entry];
    }

    private function getMaritalStatusEntry(?MaritalStatus $maritalStatus): array
    {
        $code = self::$maritalStatusFhirMap[$maritalStatus->value] ?? 'U';

        return [
            'coding' => [
                [
                    'system' => 'http://terminology.hl7.org/CodeSystem/v3-MaritalStatus',
                    'code' => $code,
                ],
            ],
        ];
    }

    private function getContactEntries(Patient $patient): array
    {
        $contacts = $patient->relationLoaded('emergencyContacts') ? $patient->emergencyContacts : null;
        if (! $contacts || $contacts->isEmpty()) {
            return [];
        }

        $entries = [];
        foreach ($contacts as $contact) {
            $entry = [];

            $relationship = $contact->relationship instanceof RelationshipType ? $contact->relationship->value : $contact->relationship;
            $code = self::$relationshipCodeMap[$relationship] ?? 'OTH';

            $entry['relationship'] = [
                [
                    'coding' => [
                        [
                            'system' => 'http://terminology.hl7.org/CodeSystem/v2-0131',
                            'code' => $code,
                        ],
                    ],
                    'text' => $relationship,
                ],
            ];

            $nameEntry = [
                'family' => $contact->name,
                'given' => [$contact->name],
            ];

            $entry['name'] = $nameEntry;

            $entry['telecom'] = [];

            $entries[] = $entry;
        }

        return $entries;
    }

    private function extractOfficialName(array $names): ?array
    {
        foreach ($names as $name) {
            if (($name['use'] ?? '') === 'official') {
                return $name;
            }
        }

        return $names[0] ?? null;
    }

    private function extractIdentifiers(array $identifiers): array
    {
        $result = [];
        $codeMap = self::getCodeToIdentifierType();

        foreach ($identifiers as $identifier) {
            $code = $identifier['type']['coding'][0]['code'] ?? '';
            $type = $codeMap[$code] ?? 'other';

            $entry = [
                'type' => $type,
                'value' => $identifier['value'],
            ];

            if (! empty($identifier['assigner']['display'])) {
                $entry['issuer'] = $identifier['assigner']['display'];
            }

            $result[] = $entry;
        }

        return $result;
    }

    private function extractEmergencyContacts(array $contacts): array
    {
        $result = [];
        $codeMap = self::getCodeToRelationship();

        foreach ($contacts as $contact) {
            $relationshipCode = $contact['relationship'][0]['coding'][0]['code'] ?? '';
            $relationship = $codeMap[$relationshipCode] ?? 'other';

            $given = $contact['name']['given'] ?? [];
            $family = $contact['name']['family'] ?? '';
            $fullName = trim(($given[0] ?? '') . ' ' . $family);

            $entry = [
                'name' => $fullName,
                'relationship' => $relationship,
            ];

            $result[] = $entry;
        }

        return $result;
    }
}
