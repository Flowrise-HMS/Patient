<?php

namespace Modules\Patient\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Patient\Database\Factories\PatientSchoolFactory;

class PatientSchool extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'patient_id',
        'school_name',
        'school_id',
        'school_address',
        'school_phone',
        'school_email',
        'school_type',
        'level',
        'class_name',
        'classroom',
        'hostel',
        'hostel_room',
        'course',
        'course_duration',
        'year_of_study',
        'admission_date',
        'graduation_date',
        'is_current',
        'is_active',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'admission_date' => 'date:Y-m-d',
        'graduation_date' => 'date:Y-m-d',
        'is_current' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByLevel($query, string $level)
    {
        return $query->where('level', $level);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('school_type', $type);
    }

    public function getDisplayNameAttribute(): string
    {
        $name = $this->school_name;

        if ($this->level && $this->class_name) {
            $name .= " - {$this->level} {$this->class_name}";
        } elseif ($this->class_name) {
            $name .= " - {$this->class_name}";
        } elseif ($this->level) {
            $name .= " - {$this->level}";
        }

        if ($this->course) {
            $name .= " ({$this->course})";
        }

        return $name;
    }

    public function getContactInfoAttribute(): string
    {
        $parts = [];

        if ($this->school_phone) {
            $parts[] = "Tel: {$this->school_phone}";
        }

        if ($this->school_email) {
            $parts[] = "Email: {$this->school_email}";
        }

        return implode(' | ', $parts);
    }

    protected static function newFactory(): PatientSchoolFactory
    {
        return PatientSchoolFactory::new();
    }
}
