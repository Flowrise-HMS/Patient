<?php

namespace Modules\Patient\Http\Requests\Api;

use Modules\Core\Http\Requests\Concerns\InjectsCurrentBranch;
use Modules\Patient\Http\Requests\PatientRequest;

/**
 * API flavour of {@see PatientRequest}: identical validation, with `branch_id`
 * taken from the caller's branch and `user_id` (portal account link) not
 * settable over the API. On update, fields are only required when sent.
 */
class PatientApiRequest extends PatientRequest
{
    use InjectsCurrentBranch;

    public function rules(): array
    {
        $rules = collect(parent::rules())->except(['user_id', 'global_uuid']);

        if ($this->isMethod('PATCH') || $this->isMethod('PUT')) {
            $rules = $rules->map(fn (array $rule): array => array_map(fn ($item) => $item === 'required' ? 'sometimes' : $item, $rule));
        }

        return $rules->all();
    }
}
