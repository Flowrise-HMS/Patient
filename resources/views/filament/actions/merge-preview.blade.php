@php
    /** @var \Modules\Patient\Models\Patient $source */
    /** @var \Modules\Patient\Models\Patient|null $target */
    /** @var array{fields: array<string, array{source: mixed, target: mixed, will_fill: bool}>, counts: array<string, int>, warnings: list<string>}|null $preview */

    $format = function (mixed $value): string {
        if ($value instanceof \BackedEnum) {
            return method_exists($value, 'getLabel') ? (string) $value->getLabel() : (string) $value->value;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('d M Y');
        }
        if (is_bool($value)) {
            return $value ? __('Yes') : __('No');
        }
        if (is_array($value)) {
            return implode(', ', array_filter(array_map(fn ($v) => is_scalar($v) ? (string) $v : null, $value)));
        }

        return filled($value) ? (string) $value : '-';
    };

    $labels = [
        'mrn' => 'MRN',
        'old_hospital_number' => 'Old hospital no.',
        'first_name' => 'First name',
        'last_name' => 'Last name',
        'middle_name' => 'Middle name',
        'title' => 'Title',
        'date_of_birth' => 'Date of birth',
        'gender' => 'Gender',
        'phone' => 'Phone',
        'email' => 'Email',
        'blood_type' => 'Blood type',
        'nationality' => 'Nationality',
    ];
@endphp

@if ($preview === null || $target === null)
    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Select the surviving patient to preview the merge.') }}</p>
@else
    <div class="space-y-4 text-sm">
        @foreach ($preview['warnings'] as $warning)
            <div class="rounded-lg border border-warning-300 bg-warning-50 p-3 text-warning-800 dark:border-warning-500/40 dark:bg-warning-500/10 dark:text-warning-200">
                {{ $warning }}
            </div>
        @endforeach

        <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-white/10">
            <table class="w-full text-left">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-white/5 dark:text-gray-400">
                    <tr>
                        <th class="px-3 py-2">{{ __('Field') }}</th>
                        <th class="px-3 py-2">{{ __('Duplicate (archived)') }}</th>
                        <th class="px-3 py-2">{{ __('Survivor (kept)') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                    @foreach ($labels as $field => $label)
                        @continue(! isset($preview['fields'][$field]))
                        @php($row = $preview['fields'][$field])
                        <tr>
                            <td class="px-3 py-2 font-medium text-gray-700 dark:text-gray-200">{{ __($label) }}</td>
                            <td class="px-3 py-2 text-gray-600 dark:text-gray-300">{{ $format($row['source']) }}</td>
                            <td class="px-3 py-2 text-gray-900 dark:text-white">
                                {{ $format($row['target']) }}
                                @if ($row['will_fill'])
                                    <span class="ml-1 rounded bg-info-100 px-1.5 py-0.5 text-xs text-info-800 dark:bg-info-500/20 dark:text-info-200">{{ __('will be filled from duplicate') }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div>
            <p class="mb-1 font-medium text-gray-700 dark:text-gray-200">{{ __('Records that will move to the survivor') }}</p>
            @if ($preview['counts'] === [])
                <p class="text-gray-500 dark:text-gray-400">{{ __('No linked records found on the duplicate.') }}</p>
            @else
                <ul class="grid grid-cols-2 gap-x-6 gap-y-1 text-gray-600 dark:text-gray-300 sm:grid-cols-3">
                    @foreach ($preview['counts'] as $label => $count)
                        <li>{{ $label }}: <span class="font-semibold text-gray-900 dark:text-white">{{ $count }}</span></li>
                    @endforeach
                </ul>
            @endif
        </div>

        <p class="text-gray-600 dark:text-gray-300">
            {{ __('MRN :source will be archived and kept as a secondary identifier on MRN :target so it stays searchable. This cannot be undone automatically.', ['source' => $source->mrn, 'target' => $target->mrn]) }}
        </p>
    </div>
@endif
