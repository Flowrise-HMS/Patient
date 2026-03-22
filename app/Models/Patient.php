<?php

namespace Modules\Patient\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Enums\Title;
use Modules\Core\Models\BaseModel;
use Modules\Core\Traits\HasAddress;
use Modules\Core\Traits\HasContact;
use Modules\Patient\Database\Factories\PatientFactory;
use Modules\Patient\Enums\BloodType;
use Modules\Patient\Enums\EducationLevel;
use Modules\Patient\Enums\Gender;
use Modules\Patient\Enums\MaritalStatus;
use Modules\Patient\Observers\PatientObserver;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

#[ObservedBy([PatientObserver::class])]
class Patient extends BaseModel implements HasMedia
{
    use HasAddress, HasContact, HasUuids, InteractsWithMedia, SoftDeletes;

    protected $keyType = 'string';

    protected $fillable = [
        'global_uuid',
        'user_id', 'branch_id', 'mrn', 'title', 'first_name', 'middle_name', 'last_name',
        'date_of_birth', 'is_date_of_birth_estimated', 'gender', 'blood_type', 'marital_status',
        'education_level', 'occupation', 'nationality', 'address', 'contact',
        'phone', 'email', 'preferred_language', 'photo', 'is_active', 'is_deceased', 'deceased_at', 'encrypted_fields', 'meta',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'title' => Title::class,
        'gender' => Gender::class,
        'blood_type' => BloodType::class,
        'marital_status' => MaritalStatus::class,
        'education_level' => EducationLevel::class,
        'date_of_birth' => 'datetime:Y-m-d H:i:s',
        'deceased_at' => 'datetime',
        'is_deceased' => 'boolean',
        'is_active' => 'boolean',
        'meta' => 'array',
        'address' => 'array',
        'contact' => 'array',
        'encrypted_fields' => 'encrypted:array',
        'phone' => 'encrypted',
        'email' => 'encrypted',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function identifiers(): HasMany
    {
        return $this->hasMany(PatientIdentifier::class);
    }

    public function emergencyContacts(): HasMany
    {
        return $this->hasMany(EmergencyContact::class);
    }

    public function schools(): HasMany
    {
        return $this->hasMany(PatientSchool::class);
    }

    public function currentSchool(): HasMany
    {
        return $this->hasMany(PatientSchool::class)->where('is_current', true);
    }

    #[Scope]
    protected function deceased(Builder $query)
    {
        return $query->where('is_deceased', true);
    }

    #[Scope]
    protected function active(Builder $query)
    {
        return $query->where('is_active', true);
    }

    public function getFullNameAttribute(): string
    {
        $parts = array_filter([
            $this->title?->getLabel(),
            $this->first_name,
            $this->middle_name,
            $this->last_name,
        ]);

        return implode(' ', $parts);
    }

    public function getAgeAttribute(): ?int
    {
        return $this->date_of_birth?->age;
    }

    public function isDeceased(): bool
    {
        return $this->is_deceased && ! empty($this->deceased_at);
    }

    protected static function newFactory(): PatientFactory
    {
        return PatientFactory::new();
    }
}
