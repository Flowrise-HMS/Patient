<?php

use Illuminate\Support\Facades\Route;
use Modules\Patient\Http\Controllers\HospitalCardController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('patients/{patient}/hospital-card', HospitalCardController::class)
        ->name('patients.hospital-card');

});
