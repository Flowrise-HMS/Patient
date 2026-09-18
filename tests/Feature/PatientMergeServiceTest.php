<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Core\Classes\Services\MediaDocumentService;
use Modules\Core\Classes\Support\PatientMergeHandlersRegistry;
use Modules\Core\Contracts\PatientMergeHandler;
use Modules\Core\Database\Factories\BranchFactory;
use Modules\Patient\Classes\Services\PatientMergeService;
use Modules\Patient\Classes\Services\PatientSearchService;
use Modules\Patient\Database\Factories\PatientFactory;
use Modules\Patient\Enums\IdentifierType;
use Modules\Patient\Events\PatientsMerged;
use Modules\Patient\Exceptions\PatientMergeException;
use Modules\Patient\Models\EmergencyContact;
use Modules\Patient\Models\Patient;
use Modules\Patient\Models\PatientIdentifier;
use Modules\Patient\Models\PatientMerge;
use Modules\Patient\Models\PatientSchool;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, DatabaseTransactions::class);

beforeEach(function () {
    $this->migrateModules(['Core', 'Patient']);
    Storage::fake('local');

    $this->branch = BranchFactory::new()->create();
    $this->actor = User::factory()->create(['branch_id' => $this->branch->id]);

    $this->source = mergeablePatient(['mrn' => 'MRN-DUP', 'first_name' => 'Ama', 'last_name' => 'Mensah', 'phone' => '0244000001', 'email' => 'dup@example.com', 'old_hospital_number' => 'OLD-1']);
    $this->target = mergeablePatient(['mrn' => 'MRN-KEEP', 'first_name' => 'Ama', 'last_name' => 'Mensah-Owusu', 'phone' => null, 'email' => 'keep@example.com', 'blood_type' => null, 'old_hospital_number' => null]);
});

afterEach(function () {
    Context::forget('current_branch_id');
});

function mergeablePatient(array $attributes = []): Patient
{
    return Patient::withoutEvents(fn () => PatientFactory::new()->create(array_merge([
        'branch_id' => test()->branch->id,
        'middle_name' => null,
    ], $attributes)));
}

function superAdminActor(): User
{
    Role::findOrCreate('super_admin', 'web');

    return tap(User::factory()->create(['branch_id' => test()->branch->id]))->assignRole('super_admin');
}

it('moves owned rows, dedupes identifiers and demotes flags', function () {
    PatientIdentifier::factory()->create(['patient_id' => $this->source->id, 'type' => IdentifierType::NATIONAL_ID->value, 'value' => 'GHA-111', 'is_primary' => true]);
    PatientIdentifier::factory()->create(['patient_id' => $this->source->id, 'type' => IdentifierType::PASSPORT->value, 'value' => 'P-222']);
    PatientIdentifier::factory()->create(['patient_id' => $this->target->id, 'type' => IdentifierType::NATIONAL_ID->value, 'value' => 'gha-111', 'is_primary' => true]);

    EmergencyContact::factory()->create(['patient_id' => $this->source->id, 'name' => 'Kofi Mensah', 'phone' => '024 111 2222']);
    EmergencyContact::factory()->create(['patient_id' => $this->source->id, 'name' => 'Yaa Asantewaa', 'phone' => '0555555555']);
    EmergencyContact::factory()->create(['patient_id' => $this->target->id, 'name' => 'kofi mensah', 'phone' => '0241112222']);

    PatientSchool::factory()->create(['patient_id' => $this->source->id, 'is_current' => true]);
    PatientSchool::factory()->create(['patient_id' => $this->target->id, 'is_current' => true]);

    $merge = app(PatientMergeService::class)->merge($this->source, $this->target, $this->actor, 'duplicate registration');

    $identifiers = PatientIdentifier::where('patient_id', $this->target->id)->get();
    expect($identifiers->where('type', IdentifierType::NATIONAL_ID->value))->toHaveCount(1)
        ->and($identifiers->firstWhere('type', IdentifierType::PASSPORT->value))->not->toBeNull()
        ->and(PatientIdentifier::where('patient_id', $this->source->id)->count())->toBe(0);

    expect(EmergencyContact::where('patient_id', $this->target->id)->count())->toBe(2)
        ->and(PatientSchool::where('patient_id', $this->target->id)->where('is_current', true)->count())->toBe(1)
        ->and(PatientSchool::where('patient_id', $this->target->id)->count())->toBe(2);

    expect($merge->moved_counts['patient_identifiers'])->toBe(['moved' => 1, 'deduped' => 1])
        ->and($merge->moved_counts['emergency_contacts'])->toBe(['moved' => 1, 'deduped' => 1])
        ->and($merge->reason)->toBe('duplicate registration');
});

it('fills blank target fields only and preserves the source numbers as identifiers', function () {
    app(PatientMergeService::class)->merge($this->source, $this->target, $this->actor);

    $target = $this->target->fresh();

    expect($target->phone)->toBe('0244000001')
        ->and($target->email)->toBe('keep@example.com')
        ->and($target->last_name)->toBe('Mensah-Owusu')
        ->and($target->old_hospital_number)->toBe('OLD-1')
        ->and($target->blood_type)->toBe($this->source->blood_type);

    $mrnIdentifier = PatientIdentifier::where('patient_id', $target->id)->where('type', IdentifierType::MRN->value)->get()
        ->first(fn (PatientIdentifier $identifier) => $identifier->value === 'MRN-DUP');

    expect($mrnIdentifier)->not->toBeNull()
        ->and($mrnIdentifier->issuer)->toBe('merge');

    $merge = PatientMerge::where('target_patient_id', $target->id)->first();
    expect($merge->filled_fields)->toContain('phone', 'old_hospital_number')
        ->not->toContain('email')
        ->and($merge->source_snapshot['mrn'])->toBe('MRN-DUP');
});

it('archives the source and logs the merge on both records', function () {
    Event::fake([PatientsMerged::class]);

    $merge = app(PatientMergeService::class)->merge($this->source, $this->target, $this->actor);

    $source = Patient::withTrashed()->find($this->source->id);

    expect($source->trashed())->toBeTrue()
        ->and($source->isMerged())->toBeTrue()
        ->and($source->merged_into_patient_id)->toBe($this->target->id)
        ->and($source->merged_by)->toBe($this->actor->id)
        ->and($source->merged_at)->not->toBeNull()
        ->and($source->is_active)->toBeFalse()
        ->and($source->mrn)->toBe('MRN-DUP')
        ->and($merge->source_mrn)->toBe('MRN-DUP')
        ->and($merge->target_mrn)->toBe('MRN-KEEP')
        ->and($this->target->fresh()->mergedFrom->pluck('id')->all())->toBe([$source->id]);

    $this->assertDatabaseHas('activity_log', ['subject_id' => $this->target->id, 'description' => 'patient_merged']);
    $this->assertDatabaseHas('activity_log', ['subject_id' => $this->source->id, 'description' => 'patient_merged_away']);

    Event::assertDispatched(PatientsMerged::class, fn (PatientsMerged $event) => $event->source->is($source) && $event->target->is($this->target));
});

it('repoints documents but leaves the activity log with the source', function () {
    $media = app(MediaDocumentService::class)->attach($this->source, [$this->fakePdf('a.pdf')], [])->first();
    $uploadLogs = DB::table('activity_log')->where('subject_id', $this->source->id)->where('description', 'document_uploaded')->count();

    app(PatientMergeService::class)->merge($this->source, $this->target, $this->actor);

    expect($media->fresh()->model_id)->toBe($this->target->id)
        ->and($this->target->fresh()->documents()->count())->toBe(1)
        ->and(DB::table('activity_log')->where('subject_id', $this->source->id)->where('description', 'document_uploaded')->count())->toBe($uploadLogs)
        ->and($uploadLogs)->toBeGreaterThan(0);
});

it('refuses invalid merges', function () {
    $service = app(PatientMergeService::class);

    expect(fn () => $service->merge($this->source, $this->source, $this->actor))->toThrow(PatientMergeException::class, 'itself');

    $trashed = mergeablePatient(['mrn' => 'MRN-TRASH']);
    $trashed->delete();
    expect(fn () => $service->merge($this->source, $trashed, $this->actor))->toThrow(PatientMergeException::class, 'deleted');

    $service->merge($this->source, $this->target, $this->actor);
    $third = mergeablePatient(['mrn' => 'MRN-THIRD']);

    expect(fn () => $service->merge(Patient::withTrashed()->find($this->source->id), $third, $this->actor))->toThrow(PatientMergeException::class, 'already been merged');
    expect(fn () => $service->merge($third, Patient::withTrashed()->find($this->source->id), $this->actor))->toThrow(PatientMergeException::class);
});

it('only lets super admins merge across branches', function () {
    $otherBranch = BranchFactory::new()->create();
    $foreign = Patient::withoutEvents(fn () => PatientFactory::new()->create(['branch_id' => $otherBranch->id, 'mrn' => 'MRN-FOREIGN', 'middle_name' => null]));

    $service = app(PatientMergeService::class);

    expect(fn () => $service->merge($this->source, $foreign, $this->actor))->toThrow(PatientMergeException::class, 'super administrator');

    $merge = $service->merge($this->source, $foreign, superAdminActor());

    expect($merge->target_patient_id)->toBe($foreign->id)
        ->and($foreign->fresh()->branch_id)->toBe($otherBranch->id)
        ->and($service->preview(mergeablePatient(['mrn' => 'MRN-X']), $foreign)['warnings'])->toHaveCount(1);
});

it('resolves merged mrns to the survivor in search', function () {
    $service = app(PatientMergeService::class);
    $service->merge($this->source, $this->target, $this->actor);

    $third = mergeablePatient(['mrn' => 'MRN-FINAL', 'last_name' => 'Mensah']);
    $service->merge($this->target, $third, $this->actor);

    $search = app(PatientSearchService::class);

    expect($search->searchExactMrn('MRN-DUP')?->id)->toBe($third->id)
        ->and($search->searchExactMrn('MRN-KEEP')?->id)->toBe($third->id)
        ->and($service->resolveSurvivor(Patient::withTrashed()->find($this->source->id))->id)->toBe($third->id)
        ->and($search->search('Mensah')->pluck('id')->all())->toBe([$third->id])
        ->and($search->searchForMergeTarget('MRN', $third)->pluck('id')->all())->toBe([]);
});

it('runs registered handlers and skips their tables in the generic repoint', function () {
    $handler = new class implements PatientMergeHandler
    {
        public array $calls = [];

        public function handledTables(): array
        {
            return ['website_booking_requests'];
        }

        public function preview(Model $source, Model $target): array
        {
            return ['Bookings' => 3];
        }

        public function handle(Model $source, Model $target, array $context): array
        {
            $this->calls[] = [$source->id, $target->id, $context['actor_id']];

            return ['website_booking_requests' => ['moved' => 3, 'deduped' => 0]];
        }
    };

    app(PatientMergeHandlersRegistry::class)->register($handler);

    $service = app(PatientMergeService::class);

    expect($service->discoverPatientIdTables())->not->toContain('website_booking_requests', 'patients', 'patient_identifiers')
        ->and($service->preview($this->source, $this->target)['counts']['Bookings'])->toBe(3);

    $merge = $service->merge($this->source, $this->target, $this->actor);

    expect($handler->calls)->toBe([[$this->source->id, $this->target->id, $this->actor->id]])
        ->and($merge->moved_counts['website_booking_requests'])->toBe(['moved' => 3, 'deduped' => 0]);
});

it('repoints clinical records through the generic path', function () {
    $this->requireModule('Clinical');

    $encounterClass = 'Modules\\Clinical\\Models\\Encounter';
    $encounter = $encounterClass::factory()->forPatient($this->source)->create();

    DB::table('clinical_canvas_layouts')->insert([
        ['id' => (string) Str::uuid(), 'user_id' => $this->actor->id, 'patient_id' => $this->source->id, 'canvas_key' => 'ward', 'context_id' => null, 'layout' => '{}', 'created_at' => now(), 'updated_at' => now()],
        ['id' => (string) Str::uuid(), 'user_id' => $this->actor->id, 'patient_id' => $this->target->id, 'canvas_key' => 'ward', 'context_id' => null, 'layout' => '{}', 'created_at' => now(), 'updated_at' => now()],
        ['id' => (string) Str::uuid(), 'user_id' => $this->actor->id, 'patient_id' => $this->source->id, 'canvas_key' => 'opd', 'context_id' => null, 'layout' => '{}', 'created_at' => now(), 'updated_at' => now()],
    ]);

    $merge = app(PatientMergeService::class)->merge($this->source, $this->target, $this->actor);

    expect($encounter->fresh()->patient_id)->toBe($this->target->id)
        ->and($merge->moved_counts['encounters']['moved'])->toBe(1)
        ->and(DB::table('clinical_canvas_layouts')->where('patient_id', $this->target->id)->count())->toBe(2)
        ->and(DB::table('clinical_canvas_layouts')->where('patient_id', $this->source->id)->count())->toBe(0)
        ->and($merge->moved_counts['clinical_canvas_layouts'])->toBe(['moved' => 1, 'deduped' => 1]);
});
