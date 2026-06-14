<?php

namespace Modules\Patient\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Patient\Http\Requests\PatientRequest;

class PatientController extends Controller
{
    public function index()
    {
        return view('patient::index');
    }

    public function create()
    {
        return view('patient::create');
    }

    public function store(PatientRequest $request)
    {
        return redirect()->route('patient.patients.index');
    }

    public function show($id)
    {
        return view('patient::show');
    }

    public function edit($id)
    {
        return view('patient::edit');
    }

    public function update(PatientRequest $request, $id)
    {
        return redirect()->route('patient.patients.index');
    }

    public function destroy($id) {}
}
