<?php

namespace Modules\Patient\Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Patient\Enums\PatientRelationshipType;
use Modules\Patient\Models\Patient;
use Modules\Patient\Models\PatientRelationship;
use Tests\TestCase;

class PatientRelationshipTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrateModules(['Core', 'Patient']);
    }

    public function test_creates_mother_child_link(): void
    {
        $mother = Patient::factory()->female()->create();
        $child = Patient::factory()->child()->create();

        $link = PatientRelationship::create([
            'subject_type' => Patient::class,
            'subject_id' => $child->id,
            'object_type' => Patient::class,
            'object_id' => $mother->id,
            'type' => PatientRelationshipType::MOTHER,
        ]);

        $this->assertSame($mother->id, $link->object_id);
        $this->assertSame(PatientRelationshipType::MOTHER, $link->type);
        $this->assertInstanceOf(Patient::class, $link->subject()->first());
        $this->assertSame($child->id, $link->subject()->first()->id);
    }

    public function test_mother_child_discovery_query(): void
    {
        $mother = Patient::factory()->female()->create();
        $child = Patient::factory()->child()->create();

        PatientRelationship::create([
            'subject_type' => Patient::class,
            'subject_id' => $child->id,
            'object_type' => Patient::class,
            'object_id' => $mother->id,
            'type' => PatientRelationshipType::MOTHER,
        ]);

        $found = PatientRelationship::query()
            ->where('type', PatientRelationshipType::MOTHER)
            ->where('subject_id', $child->id)
            ->where('subject_type', Patient::class)
            ->first();

        $this->assertNotNull($found);
        $this->assertSame($mother->id, $found->object_id);
    }

    public function test_duplicate_link_is_rejected(): void
    {
        $mother = Patient::factory()->female()->create();
        $child = Patient::factory()->child()->create();

        $attributes = [
            'subject_type' => Patient::class,
            'subject_id' => $child->id,
            'object_type' => Patient::class,
            'object_id' => $mother->id,
            'type' => PatientRelationshipType::MOTHER,
        ];

        PatientRelationship::create($attributes);

        $this->expectException(QueryException::class);
        PatientRelationship::create($attributes);
    }
}
