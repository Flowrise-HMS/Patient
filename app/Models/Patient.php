<?php

namespace Modules\Patient\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Modules\Appointment\Models\Appointment;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Payment;
use Modules\Clinical\Models\Allergy;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Models\VitalSign;
use Modules\Core\Contracts\ProvidesClientIdentity;
use Modules\Core\Enums\Title;
use Modules\Core\Models\BaseModel;
use Modules\Core\Support\ClientIdentity;
use Modules\Core\Support\ClientIdentityResolver;
use Modules\Core\Traits\HasAddress;
use Modules\Core\Traits\HasContact;
use Modules\Core\Traits\HasDocumentMedia;
use Modules\Patient\Database\Factories\PatientFactory;
use Modules\Patient\Enums\BloodType;
use Modules\Patient\Enums\EducationLevel;
use Modules\Patient\Enums\Gender;
use Modules\Patient\Enums\MaritalStatus;
use Modules\Patient\Observers\PatientObserver;
use Spatie\MediaLibrary\HasMedia;

/**
 * @property string|null $old_hospital_number
 * @property string|null $merged_into_patient_id
 * @property Carbon|null $merged_at
 * @property int|null $merged_by
 * @property-read Collection<int, Encounter> $encounters
 * @property-read Encounter|null $latestEncounter
 * @property-read Encounter|null $activeEncounter
 * @property-read VitalSign|null $latestVitals
 * @property-read Collection<int, Allergy> $allergies
 * @property-read Collection<int, Appointment> $appointments
 * @property-read Collection<int, Invoice> $invoices
 * @property-read Collection<int, Payment> $payments
 *
 * @method \Illuminate\Database\Eloquent\Relations\HasMany encounters()
 * @method \Illuminate\Database\Eloquent\Relations\HasMany diagnoses()
 * @method \Illuminate\Database\Eloquent\Relations\HasMany appointments()
 * @method \Illuminate\Database\Eloquent\Relations\HasMany insurancePolicies()
 * @method \Illuminate\Database\Eloquent\Relations\HasOne latestEncounter()
 * @method \Illuminate\Database\Eloquent\Relations\HasOne activeEncounter()
 * @method \Illuminate\Database\Eloquent\Relations\HasOne latestVitals()
 * @method \Illuminate\Database\Eloquent\Relations\HasMany allergies()
 * @method \Illuminate\Database\Eloquent\Relations\HasMany invoices()
 * @method \Illuminate\Database\Eloquent\Relations\HasMany payments()
 * @method \Illuminate\Database\Eloquent\Relations\HasMany serviceRequests()
 * @method \Illuminate\Database\Eloquent\Relations\HasMany clinicalNotes()
 * @method \Illuminate\Database\Eloquent\Relations\HasMany vitalSigns()
 */
#[ObservedBy([PatientObserver::class])]
class Patient extends BaseModel implements HasMedia, ProvidesClientIdentity
{
    use HasAddress, HasContact, HasDocumentMedia, HasFactory, HasUuids, Notifiable, SoftDeletes;

    protected $keyType = 'string';

    protected $fillable = [
        'global_uuid',
        'user_id', 'branch_id', 'mrn', 'old_hospital_number', 'title', 'first_name', 'middle_name', 'last_name',
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
        'merged_at' => 'datetime',
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

    /** @return HasMany */
    public function relationships()
    {
        return $this->hasMany(PatientRelationship::class, 'subject_id')
            ->where('subject_type', static::class);
    }

    public function currentSchool(): HasMany
    {
        return $this->hasMany(PatientSchool::class)->where('is_current', true);
    }

    /** The surviving profile this record was merged into, if any. */
    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(self::class, 'merged_into_patient_id')->withTrashed();
    }

    /** Duplicate profiles that were merged into this one. */
    public function mergedFrom(): HasMany
    {
        return $this->hasMany(self::class, 'merged_into_patient_id')->withTrashed();
    }

    public function merges(): HasMany
    {
        return $this->hasMany(PatientMerge::class, 'target_patient_id');
    }

    public function isMerged(): bool
    {
        return filled($this->merged_into_patient_id);
    }

    #[Scope]
    protected function notMerged(Builder $query)
    {
        return $query->whereNull('merged_into_patient_id');
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

    public function clientIdentity(): ClientIdentity
    {
        return ClientIdentityResolver::resolve(
            patientFullName: $this->full_name,
            patientMrn: $this->mrn,
        );
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
}
