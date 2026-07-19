<?php

namespace Modules\Patient\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Modules\Core\Http\Controllers\Api\ApiController;
use Modules\Core\Http\Responses\ApiResponse;
use Modules\Patient\Http\Resources\PatientTransformer;
use Modules\Patient\Models\Patient;

class PatientController extends ApiController
{
    public function index(): JsonResponse
    {
        $this->authorizeApi('viewAny', Patient::class);

        return ApiResponse::paginated(
            Patient::query(),
            PatientTransformer::class,
        );
    }

    public function show(string $id): JsonResponse
    {
        $patient = Patient::query()->findOrFail($id);

        $this->authorizeApi('view', $patient);

        return ApiResponse::ok(new PatientTransformer($patient));
    }
}
