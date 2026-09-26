@extends('core::print.id-card-layout', [
    'title' => $patient->full_name.' – '.__('Hospital Card'),
])

@section('content')
    @include('patient::print.partials.hospital-card')
@endsection
