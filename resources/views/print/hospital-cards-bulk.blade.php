@extends('core::print.id-card-layout', [
    'title' => __('Hospital cards (:count)', ['count' => $patients->count()]),
    'wrapperClass' => 'print-sheet',
])

@section('content')
    @foreach ($patients as $patient)
        @include('patient::print.partials.hospital-card', ['patient' => $patient])
    @endforeach
@endsection
