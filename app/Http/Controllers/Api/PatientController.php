<?php

namespace Modules\Patient\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Modules\Core\Http\Controllers\Api\ApiController;
use Modules\Core\Http\Responses\ApiResponse;
use Modules\Patient\Classes\Services\PatientService;
use Modules\Patient\Http\Requests\Api\PatientApiRequest;
use Modules\Patient\Http\Resources\PatientTransformer;
use Modules\Patient\Models\Patient;

/**
 * @group Patients
 *
 * Patient registry (branch-scoped). Identifiers such as the MRN are generated
 * on create; deleting archives the record (soft delete).
 */
class PatientController extends ApiController
{
    public function __construct(protected PatientService $patients) {}

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

    /**
     * Register a patient.
     */
    public function store(PatientApiRequest $request): JsonResponse
    {
        $this->authorizeApi('create', Patient::class);

        return ApiResponse::created(new PatientTransformer($this->patients->create($request->validated())));
    }

    /**
     * Update demographics.
     */
    public function update(PatientApiRequest $request, Patient $patient): JsonResponse
    {
        $this->authorizeApi('update', $patient);

        return ApiResponse::ok(new PatientTransformer($this->patients->update($patient, $request->validated())));
    }

    /**
     * Archive a patient record.
     */
    public function destroy(Patient $patient): JsonResponse
    {
        $this->authorizeApi('delete', $patient);

        $this->patients->delete($patient);

        return ApiResponse::noContent();
    }
}
