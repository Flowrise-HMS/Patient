<?php

namespace Modules\Patient\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Patient\Database\Factories\EmergencyContactFactory;

class EmergencyContact extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'patient_id', 'name', 'relationship', 'relationship_other', 'phone', 'alternate_phone', 'email', 'address', 'is_primary', 'can_receive_sms',
        'can_make_medical_decisions', 'note',
    ];

    protected $casts = [
        'phone' => 'encrypted',
        'alternate_phone' => 'encrypted',
        'email' => 'encrypted',
        'address' => 'encrypted',
        'is_primary' => 'boolean',
        'can_receive_sms' => 'boolean',
        'can_make_medical_decisions' => 'boolean',
    ];

    protected static function newFactory(): EmergencyContactFactory
    {
        return EmergencyContactFactory::new();
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }
}
