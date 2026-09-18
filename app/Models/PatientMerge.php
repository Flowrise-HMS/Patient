<?php

namespace Modules\Patient\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit row written for every patient merge. Deliberately not a BaseModel so
 * the branch scope never hides cross-branch merges from an auditor.
 */
class PatientMerge extends Model
{
    use HasUuids;

    protected $keyType = 'string';

    protected $fillable = [
        'source_patient_id', 'target_patient_id', 'branch_id', 'merged_by',
        'source_mrn', 'target_mrn', 'moved_counts', 'filled_fields', 'source_snapshot', 'reason',
    ];

    protected $casts = [
        'moved_counts' => 'array',
        'filled_fields' => 'array',
        'source_snapshot' => 'encrypted:array',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'source_patient_id')->withoutGlobalScopes();
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'target_patient_id')->withoutGlobalScopes();
    }

    public function mergedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'merged_by');
    }
}
