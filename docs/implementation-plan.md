# Patient Module Implementation Record

**Document Status:** Implemented (verified against code 2026-09-20)
**Module:** FlowRise HMS Patient Module (`Modules/Patient`)

This document replaces the original April 2026 implementation plan. It describes what exists in the module today so that developers can find their way around; staff-facing instructions are in [docs/user-guide/patient-management.md](../../../docs/user-guide/patient-management.md).

---

## 1. Scope

The Patient module owns the patient master record: demographics, identifiers, emergency contacts, school history, patient-to-patient relationships (mother/child), documents, duplicate merging, the hospital card, registration analytics widgets, the `GET /api/v1/patients` REST endpoints and the FHIR `Patient` transformer.

Other modules attach data to the patient by `patients.id` (UUID) and contribute relation managers to the patient view through `Modules\Core\Classes\Support\RelationManagersRegistry` or the soft-dependency list in `PatientResource::getRelations()`.

---

## 2. Database

13 migrations (`database/migrations/`):

| Table | Purpose |
|-------|---------|
| `patients` | Master record. UUID primary key, `global_uuid`, `mrn`, `old_hospital_number` (2026-09-17), names, `date_of_birth`, `is_date_of_birth_estimated`, `gender`, `blood_type`, `marital_status`, `education_level`, `occupation`, `nationality`, `phone`, `email`, `preferred_language`, `photo`, `address` (JSON), `contact` (JSON), `meta` (JSON), `is_active`, `is_deceased`, `deceased_at`, `branch_id`, `user_id`, merge columns (`merged_into_patient_id`, `merged_at`), soft deletes |
| `patient_identifiers` | Typed identifiers (`type`, `value` encrypted, `issuer`, `issuer_country`, `expiry_date`, `is_primary`, `is_verified`) |
| `emergency_contacts` | Next of kin (`name`, `relationship`, `relationship_other`, `phone`, `alternate_phone`, `email`, `address` (the last four encrypted), `is_primary`, `can_receive_sms`, `can_make_medical_decisions`, `notify_for_billing`, `note`) |
| `patient_schools` | School history (`school_name`, `school_type`, `level`, `class_name`, `course`, `is_current`, `is_active`, ...) |
| `patient_relationships` | Patient-to-patient links (`PatientRelationshipType`: mother), used by MCH for mother/child |
| `patient_merges` | Audit of merges (`source_patient_id`, `target_patient_id`, `branch_id`, `source_mrn`, `target_mrn`, `moved_counts`, `filled_fields`, `source_snapshot` encrypted, `reason`) |
| `media` (alteration) | UUID model support for Spatie Media Library (patient photo, documents) |

### Encrypted columns

`patients.phone`, `patients.email`, `patients.encrypted_fields` (JSON), `patient_identifiers.value`, `emergency_contacts.phone/alternate_phone/email/address`, `patient_merges.source_snapshot`. `date_of_birth` is **not** encrypted (it is needed for age filters and reporting).

---

## 3. Code structure (`app/`)

| Area | Contents |
|------|----------|
| `Models/` | `Patient`, `PatientIdentifier`, `EmergencyContact`, `PatientSchool`, `PatientRelationship`, `PatientMerge` |
| `Classes/Services/` | `PatientService` (CRUD, activate/deactivate, markAsDeceased, link/unlink user, school helpers, full profile), `PatientIdentifierService` (primary MRN, verify/unverify, duplicates, expiring), `EmergencyContactService` (primary contact, medical decision makers, SMS eligibility), `PatientSchoolService` (current school, transitions, education timeline), `PatientSearchService` (searchable fields, exact MRN, phone normalisation, duplicate candidates, Filament searchable attributes), `PatientMergeService` (preview, merge, discover tables with `patient_id`), `PatientAnalyticsService` (registration summary, monthly/yearly registrations, by region, top diagnoses) |
| `Enums/` | `Gender` (male, female), `BloodType`, `MaritalStatus` (single, married, divorced, widowed, separated, cohabiting, unknown), `EducationLevel` (none ... postgraduate, unknown), `IdentifierType` (mrn, national_id, passport, driver_license, birth_certificate, ssnit, voter_id, alien_id, other), `RelationshipType` (13 next-of-kin types), `PatientRelationshipType` (mother), `SchoolType`, `DocumentType` (national_id, passport, insurance_card, birth_certificate, medical_record, lab_result, prescription, referral_letter, consent_form, other) |
| `Events/` | `PatientRegistered`, `PatientUpdated`, `PatientDeactivated`, `PatientDeceased`, `PatientsMerged` |
| `Observers/` | `PatientObserver` (generates the MRN with `generate_mrn()` on create) |
| `Policies/` | `PatientPolicy` (Shield abilities + `merge` => `merge_patients`) |
| `Http/` | `HospitalCardController` (`GET /patients/{patient}/hospital-card`, PDF), `Api/PatientController` (`GET /api/v1/patients`, `GET /api/v1/patients/{id}`; registered by Core's `ApiRouteRegistrar` when the Api module is enabled), form requests, `Resources/PatientTransformer` (FHIR Patient, read/search/create/update/delete via the FHIR module) |
| `Filament/Clusters/Patient/` | `PatientCluster` (sidebar Patient Care → Patients, sort 10) and `Resources/Patients/PatientResource` with pages List / Create / View / Edit / Activities, schemas (`PatientForm` wizard, `PatientInfolist`, `PatientSchoolFormSchema`), `PatientsTable`, relation managers `SchoolsRelationManager` and `PatientDocumentsRelationManager` (extends Core `MediaDocumentsRelationManager`) |
| `Filament/Actions/` | `MergePatientAction` |
| `Filament/Imports/`, `Filament/Exports/` | `PatientImporter` (30 columns), `PatientExporter` (super-admin export) |
| `Filament/Widgets/` | Dashboard widgets: `PatientRegistrationStatsWidget`, `RecentPatientRegistrationsWidget`, `PatientRegistrationsChartWidget`, `PatientsByRegionChartWidget`, `TopDiagnosesChartWidget` (each needs `View <Widget>`) |
| `Filament/Concerns/SyncsPatientInsurance` | Keeps the Insurance policy fields on the patient form in sync when the Insurance module is enabled |
| `database/factories/` | `PatientFactory`, `PatientIdentifierFactory`, `EmergencyContactFactory`, `PatientSchoolFactory`, `PatientRelationshipFactory` |
| `database/seeders/` | `PatientDatabaseSeeder`, `PatientCustomPermissionSeeder`, `PatientShieldPermissionsSeeder` |

---

## 4. Filament behaviour

- **Form**: two-step `Wizard` (Demographics, Contact). Demographics holds Personal Information, the `identifiers` repeater, Insurance Information (fields supplied by `Modules\Insurance\Filament\Schemas\PatientInsuranceSchema` when that class exists and `insurance.enabled` is true), Additional Information and School Information (visible when the form-only toggle "Is this a student?" is on). Contact holds Contact Information, Address and the `emergencyContacts` repeater (not deletable). `PatientForm::simpleForm()` provides the short form used by the Clinical and MCH workspaces for in-place registration.
- **Table**: columns photo, MRN, old hospital number, patient name (`ClientIdentityColumn`), gender, age, phone, branch, status, balance due (Billing), registered, NHIS member (Insurance). Filters: trashed, branch, gender, status, merged, age group, registration month. Row actions: restore, view, edit, delete, activate, activities. Bulk: delete, force delete, restore, activate selected, deactivate selected, plus any actions pushed through `TableBulkActionsRegistry` (FHIR "Export Selected as FHIR").
- **List header**: Import, Export (super admin), New patient, plus registry header actions (FHIR "Export FHIR").
- **View header**: when the Clinical module is present, `Modules\Clinical\Classes\Actions\PatientActions` supplies Clinical Workspace / Timeline / View Full Profile / Medication Canvas / More Actions (with `MergePatientAction` appended); otherwise Activities, Merge, Edit. Merged profiles show an "Open surviving record" action instead.
- **Relation managers** (soft dependencies, only when the class exists): Clinical (allergies, vital signs, clinical notes, service requests, encounters, medication administrations, tasks, diagnoses), Billing (invoices, payments, deposits), Appointment (appointments); the Insurance `PatientPoliciesRelationManager` is referenced but does not exist yet, so no Policies tab renders, plus registry-provided managers (MCH pregnancy episodes, immunization records, growth measurements).

---

## 5. Permissions

Shield abilities on `Patient` (`ViewAny Patient`, `View Patient`, `Create Patient`, ...), `View PatientCluster`, widget permissions, and custom permissions from `config/config.php`: `print_hospital_card`, `discharge_patient`, `view_patient_balance`, `import_patients`, `merge_patients`. `PatientCustomPermissionSeeder` grants them to the seeded roles (merge: super_admin only).

---

## 6. Key design decisions

- **MRN**: `generate_mrn()` (Core helper) builds `<prefix>-<YYYYMMDD>-<00001>` using `CoreSettings::$mrn_prefix` (default `FR`), a cache-backed counter and a DB transaction with retries.
- **Global UUID**: every patient carries `global_uuid` for cross-system identity (FHIR).
- **Soft deletes** on patients; restore from the Trashed filter.
- **Merging** keeps the duplicate as an archived record pointing at the survivor; `PatientMergeService::discoverPatientIdTables()` moves every table with a `patient_id` column except those listed in `config('patient.merge.skip_tables')`, and other modules can register handlers through Core's `PatientMergeHandlersRegistry`.
- **Branch scoping**: `Patient` extends Core `BaseModel`, so lists are scoped to the branch selected in the top bar.

---

## 7. Tests

25 test files under `tests/` (feature: model, schools, edge cases, analytics, hospital card, FHIR API and import, REST API, relationships, exporter, old hospital number, merge action/service, documents relation manager, view header actions; unit: services, workspace form, relation manager soft boundary, FHIR transformer, policy, DTO). Run with `php artisan test --compact Modules/Patient/tests`.

---

## 8. Not implemented / ideas

- No dedicated relation-manager UI for identifiers or emergency contacts (edited via the form wizard).
- Table search over `phone`/`email` cannot match because those columns are encrypted; use name, MRN or old hospital number.
- The `patient_quick_add_enabled`, `patient_import_enabled` and `patient_hospital_card_enabled` feature toggles exist in Core settings but are not consulted by this module.
