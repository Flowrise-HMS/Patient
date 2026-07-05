<?php

namespace Modules\Patient\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Modules\Appointment\Models\Appointment;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Payment;
use Modules\Clinical\Enums\EncounterStatus;
use Modules\Clinical\Models\Allergy;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Models\VitalSign;
use Modules\Core\Enums\Title;
use Modules\Core\Models\BaseModel;
use Modules\Core\Traits\HasAddress;
use Modules\Core\Traits\HasContact;
use Modules\Insurance\Models\PatientPolicy;
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
    use HasAddress, HasContact, HasFactory, HasUuids, InteractsWithMedia, Notifiable, SoftDeletes;

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

    public function encounters(): HasMany
    {
        return $this->hasMany(Encounter::class, 'patient_id');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'patient_id');
    }

    public function insurancePolicies()
    {
        return $this->hasMany(PatientPolicy::class);
    }

    public function latestEncounter(): HasOne
    {
        return $this->hasOne(Encounter::class, 'patient_id')
            ->orderByDesc('created_at');
    }

    public function activeEncounter(): HasOne
    {
        return $this->hasOne(Encounter::class, 'patient_id')
            ->whereNotIn('status', [
                EncounterStatus::FINISHED,
                EncounterStatus::CANCELLED,
            ])
            ->orderByDesc('created_at');
    }

    public function latestVitals(): HasOne
    {
        return $this->hasOne(VitalSign::class, 'patient_id')
            ->latestOfMany('recorded_at');
    }

    public function allergies(): HasMany
    {
        return $this->hasMany(Allergy::class, 'patient_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
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

    public function getDisplayNameAttribute(): string
    {
        $fullname = $this->getFullNameAttribute();
        $mrn = $this->mrn;

        return "$fullname ($mrn)";
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

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('documents');
    }

    public function routeNotificationForMail($notification = null): ?string
    {
        return $this->email ?: null;
    }

    public function routeNotificationForSms($notification = null): ?string
    {
        return $this->phone ?: null;
    }

    protected function getPhotoUrlAttribute(): ?string
    {
        return private_url($this->photo);
    }

    public function hasPhoto(): bool
    {
        return (bool) $this->photo;
    }

    public function getDocumentsAttribute()
    {
        return $this->getMedia('documents');
    }
}
