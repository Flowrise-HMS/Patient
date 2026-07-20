<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Services\ApiRouteRegistrar;
use Modules\Patient\Http\Controllers\Api\PatientController;

ApiRouteRegistrar::register(
    routes: fn () => Route::apiResource('patients', PatientController::class)->only(['index', 'show']),
);
