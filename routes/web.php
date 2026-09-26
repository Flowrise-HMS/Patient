<?php

use Illuminate\Support\Facades\Route;
use Modules\Patient\Http\Controllers\HospitalCardController;
use Modules\Patient\Http\Controllers\HospitalCardsBulkController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('patients/hospital-cards', HospitalCardsBulkController::class)
        ->name('patients.hospital-cards.bulk');
    Route::get('patients/{patient}/hospital-card', HospitalCardController::class)
        ->name('patients.hospital-card');

});
