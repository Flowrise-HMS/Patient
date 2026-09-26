<?php

namespace Modules\Patient\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Core\Settings\FeatureSettings;
use Modules\Patient\Models\Patient;

/**
 * Prints several patient hospital cards on one sheet. Access matches the single
 * card: the feature toggle and the print_hospital_card permission; the patient
 * query keeps its usual branch scoping.
 */
class HospitalCardsBulkController extends Controller
{
    public const MAX_CARDS = 100;

    /**
     * @param  array<int, int|string>  $ids
     */
    public static function urlFor(array $ids): string
    {
        return route('patients.hospital-cards.bulk', ['ids' => array_values($ids)]);
    }

    public function __invoke(Request $request): View
    {
        abort_unless(app(FeatureSettings::class)->patient_hospital_card_enabled, 404);
        abort_unless($request->user()?->can('print_hospital_card'), 403);

        $ids = array_values(array_unique(array_filter((array) $request->query('ids', []), 'is_scalar')));

        abort_if($ids === [], 404);
        abort_if(count($ids) > self::MAX_CARDS, 422, __('Print at most :max cards at a time.', ['max' => self::MAX_CARDS]));

        $order = array_flip(array_map('strval', $ids));

        $patients = Patient::query()
            ->whereKey($ids)
            ->with('branch')
            ->get()
            ->sortBy(fn (Patient $patient): int => $order[(string) $patient->getKey()] ?? PHP_INT_MAX)
            ->values();

        abort_if($patients->isEmpty(), 404);

        return view('patient::print.hospital-cards-bulk', ['patients' => $patients]);
    }
}
