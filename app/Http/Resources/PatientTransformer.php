<?php

namespace Modules\Patient\Http\Resources;

use Illuminate\Http\Request;
use Modules\Core\Http\Resources\ApiTransformer;
use Modules\Patient\Models\Patient;

/** @property Patient $resource */
class PatientTransformer extends ApiTransformer
{
    public function toArray(Request $request): array
    {
        return $this->filterFields([
            'id' => $this->resource->id,
            'mrn' => $this->resource->mrn,
            'first_name' => $this->resource->first_name,
            'last_name' => $this->resource->last_name,
            'date_of_birth' => $this->resource->date_of_birth?->format('Y-m-d'),
            'gender' => $this->resource->gender?->value,
            'phone' => $this->resource->phone,
            'email' => $this->resource->email,
            'address' => $this->resource->address,
            'is_active' => $this->resource->is_active,
            'created_at' => $this->resource->created_at?->toIso8601String(),
            'updated_at' => $this->resource->updated_at?->toIso8601String(),
        ]);
    }

    protected function allowedFields(): array
    {
        return [
            'id',
            'mrn',
            'first_name',
            'last_name',
            'date_of_birth',
            'gender',
            'phone',
            'email',
            'address',
            'is_active',
            'created_at',
            'updated_at',
        ];
    }
}
