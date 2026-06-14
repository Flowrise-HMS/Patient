<?php

namespace Modules\Patient\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Patient\Enums\RelationshipType;

class EmergencyContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'uuid', 'exists:patients,id'],
            'name' => ['required', 'string', 'max:255'],
            'relationship' => ['required', Rule::enum(RelationshipType::class)],
            'phone' => ['required', 'string', 'max:50'],
            'alternate_phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'can_make_medical_decisions' => ['nullable', 'boolean'],
            'is_emergency_only' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'patient_id.required' => 'Patient is required.',
            'name.required' => 'Contact name is required.',
            'relationship.required' => 'Relationship type is required.',
            'phone.required' => 'Phone number is required.',
        ];
    }
}
