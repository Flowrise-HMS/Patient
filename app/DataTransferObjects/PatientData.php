<?php

namespace Modules\Patient\DataTransferObjects;

readonly class PatientData
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public ?string $globalUuid,
        public ?string $userId,
        public ?string $branchId,
        public ?string $mrn,
        public ?string $title,
        public ?string $middleName,
        public ?string $dateOfBirth,
        public ?bool $isDateOfBirthEstimated,
        public ?string $gender,
        public ?string $bloodType,
        public ?string $maritalStatus,
        public ?string $educationLevel,
        public ?string $occupation,
        public ?string $nationality,
        public ?string $phone,
        public ?string $email,
        public ?string $preferredLanguage,
        public ?string $photo,
        public ?bool $isActive,
        public ?bool $isDeceased,
        public ?string $deceasedAt,
        public ?array $address,
        public ?array $contact,
        public ?array $meta,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            firstName: $data['first_name'],
            lastName: $data['last_name'],
            globalUuid: $data['global_uuid'] ?? null,
            userId: $data['user_id'] ?? null,
            branchId: $data['branch_id'] ?? null,
            mrn: $data['mrn'] ?? null,
            title: $data['title'] ?? null,
            middleName: $data['middle_name'] ?? null,
            dateOfBirth: $data['date_of_birth'] ?? null,
            isDateOfBirthEstimated: $data['is_date_of_birth_estimated'] ?? null,
            gender: $data['gender'] ?? null,
            bloodType: $data['blood_type'] ?? null,
            maritalStatus: $data['marital_status'] ?? null,
            educationLevel: $data['education_level'] ?? null,
            occupation: $data['occupation'] ?? null,
            nationality: $data['nationality'] ?? null,
            phone: $data['phone'] ?? null,
            email: $data['email'] ?? null,
            preferredLanguage: $data['preferred_language'] ?? null,
            photo: $data['photo'] ?? null,
            isActive: $data['is_active'] ?? null,
            isDeceased: $data['is_deceased'] ?? null,
            deceasedAt: $data['deceased_at'] ?? null,
            address: $data['address'] ?? null,
            contact: $data['contact'] ?? null,
            meta: $data['meta'] ?? null,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'global_uuid' => $this->globalUuid,
            'user_id' => $this->userId,
            'branch_id' => $this->branchId,
            'mrn' => $this->mrn,
            'title' => $this->title,
            'middle_name' => $this->middleName,
            'date_of_birth' => $this->dateOfBirth,
            'is_date_of_birth_estimated' => $this->isDateOfBirthEstimated,
            'gender' => $this->gender,
            'blood_type' => $this->bloodType,
            'marital_status' => $this->maritalStatus,
            'education_level' => $this->educationLevel,
            'occupation' => $this->occupation,
            'nationality' => $this->nationality,
            'phone' => $this->phone,
            'email' => $this->email,
            'preferred_language' => $this->preferredLanguage,
            'photo' => $this->photo,
            'is_active' => $this->isActive,
            'is_deceased' => $this->isDeceased,
            'deceased_at' => $this->deceasedAt,
            'address' => $this->address,
            'contact' => $this->contact,
            'meta' => $this->meta,
        ], fn ($value) => ! is_null($value));
    }
}
