<?php

use Illuminate\Support\Facades\Route;
use Modules\Patient\Http\Controllers\HospitalCardController;
use Modules\Patient\Http\Controllers\PatientController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('patients/{patient}/hospital-card', HospitalCardController::class)
        ->name('patients.hospital-card');

    Route::resource('patients', PatientController::class)->names('patient');
});
