<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ $patient->full_name }} – {{ __('Hospital Card') }}</title>

    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        /* --- PRINT MODE --- */
        @media print {
            body {
                margin: 0;
                padding: 0;
                background: #ffffff !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            body>* {
                visibility: hidden;
            }

            .print-wrapper {
                visibility: visible;
                position: fixed;
                inset: 0;
                width: 3.375in;
                height: 2.125in;
                margin: auto;
            }

            .card {
                box-shadow: none !important;
                border: 1px solid #000 !important;
            }
        }

        /* Barcode font */
        .barcode {
            font-family: "Libre Barcode 128", monospace;
            font-size: 38px;
            line-height: 1;
            white-space: nowrap;
        }

        /* Subtle texture overlay */
        .texture {
            background-image: url("data:image/svg+xml,%3Csvg width='300' height='300' viewBox='0 0 300 300' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%23d9e1ff' fill-opacity='0.22'%3E%3Ccircle cx='8' cy='8' r='2'/%3E%3Ccircle cx='78' cy='8' r='2'/%3E%3Ccircle cx='148' cy='8' r='2'/%3E%3Ccircle cx='218' cy='8' r='2'/%3E%3Ccircle cx='288' cy='8' r='2'/%3E%3Ccircle cx='8' cy='78' r='2'/%3E%3Ccircle cx='78' cy='78' r='2'/%3E%3Ccircle cx='148' cy='78' r='2'/%3E%3Ccircle cx='218' cy='78' r='2'/%3E%3Ccircle cx='288' cy='78' r='2'/%3E%3Ccircle cx='8' cy='148' r='2'/%3E%3Ccircle cx='78'cy='148' r='2'/%3E%3Ccircle cx='148' cy='148' r='2'/%3E%3Ccircle cx='218' cy='148' r='2'/%3E%3Ccircle cx='288' cy='148' r='2'/%3E%3Ccircle cx='8' cy='218' r='2'/%3E%3Ccircle cx='78' cy='218' r='2'/%3E%3Ccircle cx='148' cy='218' r='2'/%3E%3Ccircle cx='218' cy='218' r='2'/%3E%3Ccircle cx='288' cy='218' r='2'/%3E%3Ccircle cx='8' cy='288' r='2'/%3E%3Ccircle cx='78' cy='288' r='2'/%3E%3Ccircle cx='148' cy='288' r='2'/%3E%3Ccircle cx='218' cy='288' r='2'/%3E%3Ccircle cx='288' cy='288' r='2'/%3E%3C/g%3E%3C/svg%3E");
            background-size: 180px;
        }
    </style>

    <link href="https://fonts.googleapis.com/css2?family=Libre+Barcode+128&display=swap" rel="stylesheet">
</head>

<body class="bg-gray-100 p-10">

    <div class="print-wrapper flex justify-center items-center">

        <div
            class="card w-[3.375in] h-[2.125in] rounded-xl shadow-xl overflow-hidden border border-indigo-300 relative bg-white">

            <!-- BRAND BANNER -->
            <div
                class="h-6 bg-gradient-to-r from-blue-700 to-indigo-500 text-white px-3 flex items-center justify-between">
                <span class="text-[9px] font-semibold tracking-wide uppercase">
                    {{ config('app.name') }}
                </span>

                <span class="text-[8px] opacity-90">{{ __('PATIENT CARD') }}</span>
            </div>

            <!-- BODY CONTENT -->
            <div class="texture p-2 grid grid-cols-3 gap-x-2 h-[calc(100%-1.5rem)]">

                <!-- PHOTO -->
                <div class="col-span-1 flex flex-col">
                    <div class="w-[60px] h-[60px] rounded-md overflow-hidden shadow-inner bg-gray-200">
                        @php
                            $photo = $patient->photo_url;
                        @endphp

                        @if($photo)
                            <img src="{{ $photo }}" class="w-[60px] h-[60px] object-cover" alt="" />
                        @else
                            <div class="w-[60px] h-[60px] flex items-center justify-center text-[8px] text-gray-600">{{ __('No Photo') }}
                            </div>
                        @endif
                    </div>

                    <span class="mt-1 text-[9px] text-gray-700 font-medium">
                        {{ strtoupper($patient->gender?->value ?? '') }}{{ $patient->age !== null ? ', '.$patient->age.' '.\Illuminate\Support\Str::plural('Year', $patient->age) : '' }}
                    </span>

                    <span class="text-[8px] text-gray-500">
                        {{ $patient->getCity() ?? '' }}
                    </span>
                </div>

                <!-- DETAILS -->
                <div class="col-span-2 flex flex-col justify-between">

                    <div>
                        <p class="text-[12px] leading-tight font-bold text-gray-900">{{ $patient->full_name }}</p>

                        <p class="text-[9px] mt-[2px] text-gray-700">
                            <span class="font-medium">{{ __('Hospital No:') }}</span>
                            <span class="font-mono">{{ $patient->mrn }}</span>
                        </p>

                        <p class="text-[9px] text-gray-700">
                            <span class="font-medium">{{ __('DOB:') }}</span>
                            {{ $patient->date_of_birth?->format('M d, Y') }}
                        </p>

                        <p class="text-[9px] text-gray-700">
                            <span class="font-medium">{{ __('Phone:') }}</span>
                            {{ $patient->phone ?? '' }}
                        </p>

                        @if(!empty($patient->meta['ghana_card_id'] ?? null))
                            <p class="text-[9px] text-gray-700">
                                <span class="font-medium">{{ __('Ghana Card:') }}</span>
                                {{ $patient->meta['ghana_card_id'] }}
                            </p>
                        @endif

                        @if(!empty($patient->meta['has_insurance'] ?? null))
                            <span
                                class="inline-block mt-1 px-2 py-[1px] text-[8px] font-semibold bg-green-100 text-green-700 rounded-full">
                                {{ __('Insured') }}
                            </span>
                        @endif
                    </div>
                    <div class="flex-row items-center nowrap text-[9px] text-gray-700">
                        <small>{{ __('Keep this card carefully') }}</small><br>
                        <small>{{ __('Bring it each time you attend Hospital') }}</small>
                    </div>
                </div>
                <div class="flex items-center mt-1 col-span-3">
                    <div class="barcode leading-none">
                        *{{ $patient->mrn }}*
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        window.onload = () => setTimeout(() => window.print(), 500);
    </script>

</body>

</html>
