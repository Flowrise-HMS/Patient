<?php

namespace Modules\Patient\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Patient\Enums\IdentifierType;

class PatientIdentifierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $identifierId = $this->route('identifier')?->id;

        return [
            'patient_id' => ['required', 'uuid', 'exists:patients,id'],
            'type' => ['required', Rule::enum(IdentifierType::class)],
            'value' => ['required', 'string', 'max:255', Rule::unique('patient_identifiers', 'value')->ignore($identifierId)],
            'issuer' => ['nullable', 'string', 'max:100'],
            'issuer_country' => ['nullable', 'string', 'max:10'],
            'expiry_date' => ['nullable', 'date', 'after:today'],
            'is_primary' => ['nullable', 'boolean'],
            'is_verified' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'patient_id.required' => 'Patient is required.',
            'patient_id.exists' => 'Selected patient does not exist.',
            'type.required' => 'Identifier type is required.',
            'value.required' => 'Identifier value is required.',
            'value.unique' => 'This identifier value is already in use.',
            'expiry_date.after' => 'Expiry date must be in the future.',
        ];
    }
}
