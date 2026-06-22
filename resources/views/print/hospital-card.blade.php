@extends('core::print.id-card-layout', [
    'title' => $patient->full_name.' – '.__('Hospital Card'),
])

@section('content')
    <div class="id-card">
        <div class="id-card__banner id-card__banner--patient">
            <span class="id-card__banner-title">{{ config('app.name') }}</span>
            <span class="id-card__banner-label">{{ __('PATIENT CARD') }}</span>
        </div>

        <div class="id-card__body">
            <div class="id-card__photo-col">
                <div class="id-card__photo">
                    @if($patient->hasPhoto())
                        <img src="{{ $patient->photo_url }}" alt="" />
                    @else
                        <div class="id-card__photo-placeholder">{{ __('No Photo') }}</div>
                    @endif
                </div>

                <span class="id-card__meta">
                    {{ strtoupper($patient->gender?->value ?? '') }}{{ $patient->age !== null ? ', '.$patient->age.' '.\Illuminate\Support\Str::plural('Year', $patient->age) : '' }}
                </span>

                <span class="id-card__meta-sub">
                    {{ $patient->getCity() ?? '' }}
                </span>
            </div>

            <div class="id-card__details-col">
                <div>
                    <p class="id-card__name">{{ $patient->full_name }}</p>

                    <p class="id-card__line id-card__line--spaced">
                        <span class="id-card__label">{{ __('Hospital No:') }}</span>
                        <span class="id-card__mono">{{ $patient->mrn }}</span>
                    </p>

                    <p class="id-card__line">
                        <span class="id-card__label">{{ __('DOB:') }}</span>
                        {{ $patient->date_of_birth?->format('M d, Y') }}
                    </p>

                    <p class="id-card__line">
                        <span class="id-card__label">{{ __('Phone:') }}</span>
                        {{ $patient->phone ?? '' }}
                    </p>

                    @if(!empty($patient->meta['ghana_card_id'] ?? null))
                        <p class="id-card__line">
                            <span class="id-card__label">{{ __('Ghana Card:') }}</span>
                            {{ $patient->meta['ghana_card_id'] }}
                        </p>
                    @endif

                    @if(!empty($patient->meta['has_insurance'] ?? null))
                        <span class="id-card__badge">{{ __('Insured') }}</span>
                    @endif
                </div>

                <div class="id-card__footnote">
                    <small>{{ __('Keep this card carefully') }}</small><br>
                    <small>{{ __('Bring it each time you attend Hospital') }}</small>
                </div>
            </div>

            <div class="id-card__barcode-row">
                <div class="barcode">*{{ $patient->mrn }}*</div>
            </div>
        </div>
    </div>
@endsection
