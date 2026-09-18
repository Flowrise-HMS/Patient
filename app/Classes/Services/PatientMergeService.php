<?php

namespace Modules\Patient\Classes\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Core\Classes\Support\PatientMergeHandlersRegistry;
use Modules\Core\Support\SuperAdmin;
use Modules\Patient\Enums\IdentifierType;
use Modules\Patient\Events\PatientsMerged;
use Modules\Patient\Exceptions\PatientMergeException;
use Modules\Patient\Models\Patient;
use Modules\Patient\Models\PatientIdentifier;
use Modules\Patient\Models\PatientMerge;

/**
 * Merges a duplicate patient profile (source) into the surviving one (target).
 *
 * Module-registered handlers move rows that need special care, then every
 * remaining `patient_id` column in the database is repointed generically so new
 * modules are covered without touching this class. The source is soft-deleted
 * and marked with `merged_into_patient_id`; nothing is hard-deleted except rows
 * that would violate a unique key on the target.
 */
class PatientMergeService
{
    /**
     * Core-owned polymorphic tables that reference patients. (`notifications`
     * is deliberately absent: its morph id column is an integer, so UUID
     * patients never appear there.)
     *
     * @var array<string, string> table => morph column prefix
     */
    protected array $morphTables = [
        'media' => 'model',
    ];

    public function __construct(
        protected PatientMergeHandlersRegistry $handlers,
    ) {}

    /**
     * @return array{
     *     fields: array<string, array{source: mixed, target: mixed, will_fill: bool}>,
     *     counts: array<string, int>,
     *     warnings: list<string>,
     * }
     */
    public function preview(Patient $source, Patient $target): array
    {
        $fields = [];
        foreach (array_merge(['mrn', 'old_hospital_number', 'first_name', 'last_name'], $this->fillableFields()) as $field) {
            $sourceValue = $source->getAttribute($field);
            $targetValue = $target->getAttribute($field);

            $fields[$field] = [
                'source' => $sourceValue,
                'target' => $targetValue,
                'will_fill' => in_array($field, $this->fillableFields(), true) && blank($targetValue) && filled($sourceValue),
            ];
        }

        $counts = [];
        foreach ($this->handlers->all() as $handler) {
            foreach ($handler->preview($source, $target) as $label => $count) {
                $counts[$label] = ($counts[$label] ?? 0) + (int) $count;
            }
        }

        foreach ($this->discoverPatientIdTables() as $table) {
            $count = DB::table($table)->where('patient_id', $source->getKey())->count();
            if ($count > 0) {
                $counts[Str::headline($table)] = $count;
            }
        }

        foreach ($this->morphTables as $table => $prefix) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $count = DB::table($table)
                ->where("{$prefix}_type", $source->getMorphClass())
                ->where("{$prefix}_id", $source->getKey())
                ->count();

            if ($count > 0) {
                $counts[$table === 'media' ? 'Documents' : Str::headline($table)] = $count;
            }
        }

        $counts = array_filter($counts, fn (int $count): bool => $count > 0);

        $warnings = [];
        if ($source->branch_id !== $target->branch_id) {
            $warnings[] = __('The two profiles belong to different branches. Moved records keep their original branch, so the surviving profile will show history from both.');
        }

        return ['fields' => $fields, 'counts' => $counts, 'warnings' => $warnings];
    }

    public function merge(Patient $source, Patient $target, Authenticatable $actor, ?string $reason = null): PatientMerge
    {
        return DB::transaction(function () use ($source, $target, $actor, $reason): PatientMerge {
            $source = $this->lockedPatient($source->getKey());
            $target = $this->lockedPatient($target->getKey());

            $this->assertMergeable($source, $target, $actor);

            $snapshot = $source->load(['identifiers', 'emergencyContacts'])->toArray();
            $context = ['actor_id' => $actor->getAuthIdentifier()];
            $counts = [];

            foreach ($this->handlers->all() as $handler) {
                foreach ($handler->handle($source, $target, $context) as $table => $count) {
                    $counts[$table] = $count;
                }
            }

            foreach ($this->discoverPatientIdTables() as $table) {
                $counts[$table] = $this->repointForeignKey($table, $source, $target);
            }

            foreach ($this->morphTables as $table => $prefix) {
                if (Schema::hasTable($table)) {
                    $counts[$table] = $this->repointMorph($table, $prefix, $source, $target);
                }
            }

            $filled = $this->fillBlankTargetFields($source, $target);
            $this->preserveSourceIdentifiers($source, $target);
            $this->markSourceMerged($source, $target, $actor);

            $merge = PatientMerge::create([
                'source_patient_id' => $source->getKey(),
                'target_patient_id' => $target->getKey(),
                'branch_id' => $target->branch_id,
                'merged_by' => $actor->getAuthIdentifier(),
                'source_mrn' => $source->mrn,
                'target_mrn' => $target->mrn,
                'moved_counts' => $counts,
                'filled_fields' => $filled,
                'source_snapshot' => $snapshot,
                'reason' => $reason,
            ]);

            activity('patient')
                ->causedBy($actor)
                ->performedOn($target)
                ->withProperties(['source_patient_id' => $source->getKey(), 'source_mrn' => $source->mrn, 'moved_counts' => $counts, 'filled_fields' => $filled])
                ->log('patient_merged');

            activity('patient')
                ->causedBy($actor)
                ->performedOn($source)
                ->withProperties(['target_patient_id' => $target->getKey(), 'target_mrn' => $target->mrn])
                ->log('patient_merged_away');

            event(new PatientsMerged($source, $target, $merge));

            return $merge;
        });
    }

    public function assertMergeable(Patient $source, Patient $target, Authenticatable $actor): void
    {
        if ($source->is($target)) {
            throw PatientMergeException::sameRecord();
        }

        if ($source->isMerged()) {
            throw PatientMergeException::sourceAlreadyMerged();
        }

        if ($target->isMerged()) {
            throw PatientMergeException::targetAlreadyMerged();
        }

        if ($target->trashed()) {
            throw PatientMergeException::targetTrashed();
        }

        if ($this->resolveSurvivor($target)->is($source)) {
            throw PatientMergeException::wouldCreateCycle();
        }

        if ($source->branch_id !== $target->branch_id && ! SuperAdmin::check($actor)) {
            throw PatientMergeException::crossBranchNotAllowed();
        }
    }

    /**
     * Follow the merge chain to the live profile a (possibly merged) patient now lives under.
     */
    public function resolveSurvivor(Patient $patient, int $maxDepth = 10): Patient
    {
        $current = $patient;

        for ($depth = 0; $depth < $maxDepth && $current->isMerged(); $depth++) {
            $next = Patient::query()->withoutGlobalScopes()->find($current->merged_into_patient_id);

            if ($next === null) {
                break;
            }

            $current = $next;
        }

        return $current;
    }

    /**
     * Every table with a `patient_id` column, minus `patients`, handler-owned tables and configured skips.
     *
     * @return list<string>
     */
    public function discoverPatientIdTables(): array
    {
        $excluded = array_merge(['patients'], $this->handlers->handledTables(), (array) config('patient.merge.skip_tables', []));

        $tables = collect(Schema::getTableListing(schema: DB::connection()->getDatabaseName(), schemaQualified: false))
            ->unique()
            ->filter(fn (string $table): bool => ! in_array($table, $excluded, true) && Schema::hasColumn($table, 'patient_id'))
            ->values()
            ->all();

        sort($tables);

        return $tables;
    }

    /**
     * @return array{moved: int, deduped: int}
     */
    protected function repointForeignKey(string $table, Patient $source, Patient $target): array
    {
        try {
            $moved = DB::transaction(fn (): int => DB::table($table)
                ->where('patient_id', $source->getKey())
                ->update(['patient_id' => $target->getKey()]));

            return ['moved' => $moved, 'deduped' => 0];
        } catch (UniqueConstraintViolationException) {
            // A unique key on the target collides; fall back to row-by-row and drop the duplicates.
        }

        $moved = 0;
        $deduped = 0;

        foreach (DB::table($table)->where('patient_id', $source->getKey())->pluck('id') as $id) {
            try {
                DB::transaction(fn () => DB::table($table)->where('id', $id)->update(['patient_id' => $target->getKey()]));
                $moved++;
            } catch (UniqueConstraintViolationException) {
                DB::table($table)->where('id', $id)->delete();
                $deduped++;
            }
        }

        return ['moved' => $moved, 'deduped' => $deduped];
    }

    protected function repointMorph(string $table, string $prefix, Patient $source, Patient $target): int
    {
        return DB::table($table)
            ->where("{$prefix}_type", $source->getMorphClass())
            ->where("{$prefix}_id", $source->getKey())
            ->update(["{$prefix}_id" => $target->getKey()]);
    }

    /**
     * @return list<string>
     */
    protected function fillBlankTargetFields(Patient $source, Patient $target): array
    {
        $filled = [];

        foreach ($this->fillableFields() as $field) {
            if (blank($target->getAttribute($field)) && filled($source->getAttribute($field))) {
                $target->setAttribute($field, $source->getAttribute($field));
                $filled[] = $field;
            }
        }

        $mergedMeta = array_replace($source->meta ?? [], $target->meta ?? []);
        if ($mergedMeta !== ($target->meta ?? [])) {
            $target->meta = $mergedMeta;
            $filled[] = 'meta';
        }

        if ($filled !== []) {
            $target->save();
        }

        return $filled;
    }

    /**
     * Keep the duplicate's numbers findable from the survivor.
     */
    protected function preserveSourceIdentifiers(Patient $source, Patient $target): void
    {
        $existing = PatientIdentifier::query()
            ->where('patient_id', $target->getKey())
            ->get()
            ->map(fn (PatientIdentifier $identifier): string => $this->identifierKey($identifier->type, $identifier->value))
            ->flip();

        $candidates = [];

        if (filled($source->mrn)) {
            $candidates[] = [IdentifierType::MRN, $source->mrn, 'merge', "Former MRN of merged profile {$source->getKey()}"];
        }

        if (filled($source->old_hospital_number) && $source->old_hospital_number !== $target->old_hospital_number) {
            $candidates[] = [IdentifierType::OTHER, $source->old_hospital_number, 'legacy_hospital_number', "Old hospital number of merged profile {$source->getKey()}"];
        }

        foreach ($candidates as [$type, $value, $issuer, $note]) {
            if ($existing->has($this->identifierKey($type, $value))) {
                continue;
            }

            PatientIdentifier::create([
                'patient_id' => $target->getKey(),
                'type' => $type->value,
                'value' => $value,
                'issuer' => $issuer,
                'is_primary' => false,
                'is_verified' => true,
                'verified_at' => now(),
                'note' => $note,
            ]);
        }
    }

    protected function markSourceMerged(Patient $source, Patient $target, Authenticatable $actor): void
    {
        $source->forceFill([
            'merged_into_patient_id' => $target->getKey(),
            'merged_at' => now(),
            'merged_by' => $actor->getAuthIdentifier(),
            'is_active' => false,
        ])->save();

        $source->delete();
    }

    protected function lockedPatient(string $id): Patient
    {
        return Patient::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($id);
    }

    /**
     * @return list<string>
     */
    protected function fillableFields(): array
    {
        return array_values((array) config('patient.merge.fillable_fields', []));
    }

    protected function identifierKey(mixed $type, mixed $value): string
    {
        $type = $type instanceof \BackedEnum ? $type->value : (string) $type;

        return $type.'|'.mb_strtolower(trim((string) $value));
    }
}
