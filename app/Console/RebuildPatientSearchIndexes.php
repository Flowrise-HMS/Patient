<?php

namespace Modules\Patient\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Modules\Patient\Models\EmergencyContact;
use Modules\Patient\Models\Patient;
use Modules\Patient\Models\PatientIdentifier;

/**
 * Recomputes the blind-index columns used to search encrypted phone, email
 * and identifier values. Run after adding the columns or rotating APP_KEY.
 */
class RebuildPatientSearchIndexes extends Command
{
    protected $signature = 'patients:rebuild-search-indexes
        {--only=* : Limit to patients, contacts and/or identifiers}
        {--chunk=500 : Rows per chunk}';

    protected $description = 'Rebuild the searchable blind indexes for patient phone numbers, emails and identifiers';

    public function handle(): int
    {
        $only = array_map('strval', (array) $this->option('only'));
        $chunk = max(1, (int) $this->option('chunk'));

        $targets = [
            'patients' => fn (): Builder => Patient::query()->withoutGlobalScopes()->withTrashed(),
            'contacts' => fn (): Builder => EmergencyContact::query()->withoutGlobalScopes(),
            'identifiers' => fn (): Builder => PatientIdentifier::query()->withoutGlobalScopes(),
        ];

        foreach ($targets as $name => $query) {
            if ($only !== [] && ! in_array($name, $only, true)) {
                continue;
            }

            $updated = 0;
            $scanned = 0;

            $query()->orderBy('id')->chunkById($chunk, function ($models) use (&$updated, &$scanned): void {
                foreach ($models as $model) {
                    $scanned++;

                    if ($model->refreshBlindIndexes(force: true)) {
                        $model->saveQuietly();
                        $updated++;
                    }
                }
            });

            $this->components->info("{$name}: {$scanned} scanned, {$updated} updated.");
        }

        return self::SUCCESS;
    }
}
