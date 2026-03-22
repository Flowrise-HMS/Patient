<?php

namespace Modules\Patient\Observers;

use Illuminate\Support\Str;
use Modules\Patient\Models\Patient;

class PatientObserver
{
    public function creating(Patient $patient)
    {
        if (! $patient->global_uuid) {
            $patient->global_uuid = Str::uuid();
        }
        if (! $patient->mrn) {
            $patient->mrn = generate_mrn();
        }
    }

    /**
     * Handle the Patient "created" event.
     */
    public function created(Patient $patient): void {}

    /**
     * Handle the Patient "updated" event.
     */
    public function updated(Patient $patient): void {}

    /**
     * Handle the Patient "deleted" event.
     */
    public function deleted(Patient $patient): void {}

    /**
     * Handle the Patient "restored" event.
     */
    public function restored(Patient $patient): void {}

    /**
     * Handle the Patient "force deleted" event.
     */
    public function forceDeleted(Patient $patient): void {}
}
