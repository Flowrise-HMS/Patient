<?php

namespace Modules\Patient\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Patient\Database\Factories\PatientIdentifierFactory;

class PatientIdentifier extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = ['patient_id', 'type', 'value', 'issuer', 'issuer_country', 'is_primary', 'is_verified',
        'verified_at', 'verified_by', 'issue_date', 'expiry_date', 'note'];

    protected $casts = [
        'value' => 'encrypted', // README::since it can be the national ID
        'is_primary' => 'boolean',
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
        'issue_date' => 'date',
        'expiry_date' => 'date',
    ];

    protected static function newFactory(): PatientIdentifierFactory
    {
        return PatientIdentifierFactory::new();
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
