<?php

namespace Modules\Patient\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Modules\Core\Models\BaseModel;
use Modules\Patient\Enums\PatientRelationshipType;

class PatientRelationship extends BaseModel
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    protected $fillable = [
        'subject_type',
        'subject_id',
        'object_type',
        'object_id',
        'type',
        'created_by',
    ];

    protected $casts = [
        'type' => PatientRelationshipType::class,
    ];

    protected static function bootBelongsToBranch(): void
    {
        // patient_relationships has no branch_id — opt out of the branch scope.
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function object(): MorphTo
    {
        return $this->morphTo();
    }
}
