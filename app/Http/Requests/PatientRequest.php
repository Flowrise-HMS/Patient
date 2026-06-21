<?php

namespace Modules\Patient\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Patient\Enums\BloodType;
use Modules\Patient\Enums\EducationLevel;
use Modules\Patient\Enums\Gender;
use Modules\Patient\Enums\MaritalStatus;
use Modules\Core\Enums\Title;

class PatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $patientId = $this->route('patient')?->id ?? $this->route('patient');

        return [
            'global_uuid' => ['nullable', 'uuid'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'branch_id' => ['nullable', 'uuid', 'exists:branches,id'],
            'mrn' => ['nullable', 'string', 'max:50', Rule::unique('patients', 'mrn')->ignore($patientId)],
            'title' => ['nullable', Rule::enum(Title::class)],
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'is_date_of_birth_estimated' => ['nullable', 'boolean'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'blood_type' => ['nullable', Rule::enum(BloodType::class)],
            'marital_status' => ['nullable', Rule::enum(MaritalStatus::class)],
            'education_level' => ['nullable', Rule::enum(EducationLevel::class)],
            'occupation' => ['nullable', 'string', 'max:255'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'preferred_language' => ['nullable', 'string', 'max:50'],
            'photo' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
            'is_deceased' => ['nullable', 'boolean'],
            'deceased_at' => ['nullable', 'date', 'after_or_equal:date_of_birth'],
            'address' => ['nullable', 'array'],
            'address.street' => ['nullable', 'string', 'max:255'],
            'address.city' => ['nullable', 'string', 'max:100'],
            'address.district' => ['nullable', 'string', 'max:100'],
            'address.region' => ['nullable', 'string', 'max:100'],
            'address.country' => ['nullable', 'string', 'max:100'],
            'address.postal_code' => ['nullable', 'string', 'max:20'],
            'contact' => ['nullable', 'array'],
            'contact.phone' => ['nullable', 'string', 'max:50'],
            'contact.email' => ['nullable', 'email', 'max:255'],
            'meta' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required' => 'First name is required.',
            'last_name.required' => 'Last name is required.',
            'mrn.unique' => 'This Medical Record Number is already assigned to another patient.',
            'date_of_birth.before_or_equal' => 'Date of birth cannot be in the future.',
            'deceased_at.after_or_equal' => 'Date of death cannot be before date of birth.',
            'email.email' => 'Please provide a valid email address.',
        ];
    }
}
