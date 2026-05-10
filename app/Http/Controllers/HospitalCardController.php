<?php

namespace Modules\Patient\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Modules\Patient\Models\Patient;

class HospitalCardController extends Controller
{
    public function __invoke(Patient $patient): View|Response
    {
        $this->authorize('view', $patient);

        abort_unless(request()->user()?->can('print_hospital_card'), 403);

        return view('patient::print.hospital-card', [
            'patient' => $patient->loadMissing(['branch']),
        ]);
    }
}
