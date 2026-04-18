<?php

namespace Modules\Patient\Filament\Clusters\Patient\Resources\Patients\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;
use Modules\Core\Enums\Title;
use Modules\Core\Rules\GhanaCard;
use Modules\Patient\Enums\BloodType;
use Modules\Patient\Enums\DocumentType;
use Modules\Patient\Enums\EducationLevel;
use Modules\Patient\Enums\Gender;
use Modules\Patient\Enums\IdentifierType;
use Modules\Patient\Enums\MaritalStatus;
use Modules\Patient\Enums\RelationshipType;
use Nnjeim\World\Models\City;
use Nnjeim\World\Models\Country;
use Nnjeim\World\Models\State;
use Ysfkaya\FilamentPhoneInput\Forms\PhoneInput;

class PatientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components(static::getSteps());
    }

    public static function getSteps(): array
    {
        return [
            Wizard::make([
                Step::make('Demographics')
                    ->icon('heroicon-o-user')
                    ->description('Basic patient information')
                    ->schema([
                        static::demographicsSection(),
                    ]),

                Step::make('Contact')
                    ->icon('heroicon-o-phone')
                    ->description('How to reach the patient')
                    ->schema([
                        static::contactSection(),
                        static::addressSection(),
                    ]),

                Step::make('Emergency Contact')
                    ->icon('heroicon-o-exclamation-circle')
                    ->description('Who to contact in emergencies')
                    ->schema([
                        Repeater::make('emergencyContacts')
                            ->relationship('emergencyContacts')
                            ->schema([
                                static::emergencyContactSection(),
                            ])
                            ->defaultItems(1)
                            ->deletable(false),
                    ]),
                Step::make('Additional')
                    ->icon('heroicon-o-information-circle')
                    ->description('Additional patient details')
                    ->schema([
                        static::additionalSection(),
                    ]),

                Step::make('Identifiers')
                    ->icon('heroicon-o-identification')
                    ->description('Patient identification documents')
                    ->schema([
                        Repeater::make('identifiers')
                            ->relationship('identifiers')
                            ->schema([
                                static::identifiersSection(),
                            ])
                            ->deletable(false),
                    ]),

            ])
                ->skippable()
                ->columnSpanFull(),
        ];
    }

    public static function demographicsSection(): Section
    {
        return Section::make('Personal Information')
            ->description('Required information for patient registration')
            ->columnSpanFull()
            ->schema([
                TextInput::make('mrn')
                    ->label('Medical Record Number')
                    ->disabled()
                    ->dehydrated()
                    ->prefixIcon('heroicon-m-identification')
                    ->hint('Auto-generated')
                    ->columnSpanFull(),

                Grid::make(4)->schema([
                    Select::make('title')
                        ->label('Title')
                        ->options(Title::class)
                        ->searchable()
                        ->preload()
                        ->columnSpan('sm'),

                    TextInput::make('first_name')
                        ->label('First Name')
                        ->required()
                        ->placeholder('Enter first name')
                        ->autofocus(),

                    TextInput::make('middle_name')
                        ->label('Middle Name')
                        ->placeholder('Enter middle name (optional)'),

                    TextInput::make('last_name')
                        ->label('Last Name')
                        ->required()
                        ->placeholder('Enter last name'),
                ]),

                Grid::make(3)->schema([
                    DateTimePicker::make('date_of_birth')
                        ->label('Date of Birth')
                        ->required()
                        ->native(false)
                        ->displayFormat('d M Y')
                        ->maxDate(now())
                        ->seconds(false)
                        ->live(debounce: 500)
                        ->afterStateUpdated(function (Set $set, ?string $state) {
                            if ($state) {
                                $age = Carbon::parse($state)->age;
                                $set('calculated_age', $age);
                            }
                        }),

                    TextEntry::make('calculated_age')
                        ->label('Age')
                        ->state(function (Get $get): string {
                            $dob = $get('date_of_birth');
                            if ($dob) {
                                return Carbon::parse($dob)->age.' years old';
                            }

                            return '-';
                        })
                        ->columnSpan(1),

                    Select::make('gender')
                        ->label('Gender')
                        ->required()
                        ->options(Gender::class)
                        ->searchable()
                        ->columnSpan(1),
                ]),

                FileUpload::make('photo')
                    ->visibility('private')
                    ->image(),

                Toggle::make('is_date_of_birth_estimated')
                    ->label('Date of birth is estimated')
                    ->helperText('Check if the exact date is not known')
                    ->default(false)
                    ->columnSpanFull(),
            ]);
    }

    public static function contactSection(): Section
    {
        return Section::make('Contact Information')
            ->description('Primary contact details')
            ->columnSpanFull()
            ->columns(3)
            ->schema([
                PhoneInput::make('phone')
                    ->label('Phone Number')
                    ->required()
                    ->defaultCountry(config('core.default_country_code', 'GH')),
                TextInput::make('email')
                    ->label('Email Address')
                    ->email()
                    ->placeholder('patient@example.com'),
                Select::make('preferred_language')
                    ->label('Preferred Language')
                    ->options([
                        'english' => 'English',
                        'twi' => 'Twi',
                        'ga' => 'Ga',
                        'ewe' => 'Ewe',
                        'dagbani' => 'Dagbani',
                        'hausa' => 'Hausa',
                    ])
                    ->default('english'), // TODO:To be changed to support global locale
            ]);
    }

    public static function addressSection(): Section
    {
        return Section::make('Address')
            ->description('Patient residential address')
            ->collapsed()
            ->schema([
                TextInput::make('street')
                    ->label('Street Address')
                    ->placeholder('House number, street name')
                    ->columnSpanFull(),
                Grid::make()
                    ->columns(3)
                    ->schema([
                        TextInput::make('city')
                            ->label('City/Town')
                            ->placeholder('e.g., Accra')
                            ->dataList(fn (Get $get) => City::where('country_code', config('core.default_country_code', 'GH'))
                                ->pluck('name')
                                ->toArray()
                            ),

                        TextInput::make('district')
                            ->label('District')
                            ->placeholder('e.g., Kumasi')
                            ->columnSpan(1),

                        Select::make('region')
                            ->label('Region')
                            ->options(fn (Get $get) => State::where('country_code', config('core.default_country_code', 'GH'))
                                ->pluck('name')->toArray())
                            ->searchable()
                            ->columnSpan(1),
                    ]),
            ]);
    }

    public static function additionalSection(): Section
    {
        return Section::make('Additional Information')
            ->description('Optional patient details')
            ->schema([
                Grid::make()->schema([
                    Select::make('blood_type')
                        ->label('Blood Type')
                        ->options(BloodType::class)
                        ->searchable()
                        ->columnSpan(1),

                    Select::make('marital_status')
                        ->label('Marital Status')
                        ->options(MaritalStatus::class)
                        ->columnSpan(1),

                    Select::make('education_level')
                        ->label('Education Level')
                        ->options(EducationLevel::class)
                        ->columnSpan(1),

                    TextInput::make('occupation')
                        ->label('Occupation')
                        ->placeholder('e.g., Teacher, Engineer')
                        ->columnSpan(1),
                ]),
                Select::make('nationality')
                    ->label('Nationality')
                    ->default(config('core.default_country_code', 'GH'))
                    ->options(Country::pluck('name', 'iso2')?->toArray() ?? [])
                    ->live()
                    ->preload()
                    ->searchable(),
            ]);
    }

    public static function emergencyContactSection(): Section
    {
        return Section::make('Emergency Contact')
            ->description('Who to contact in case of emergency')
            ->columns(2)
            ->schema([
                TextInput::make('name')
                    ->label('Contact Name')
                    ->placeholder('Full name of emergency contact')
                    ->columnSpanFull(),

                Select::make('relationship')
                    ->label('Relationship')
                    ->options(RelationshipType::class)
                    ->searchable(),

                PhoneInput::make('phone')
                    ->label('Contact Phone')
                    ->required()
                    ->defaultCountry(config('core.default_country_code', 'GH')),
                PhoneInput::make('alternate_phone')
                    ->label('Alternative Phone')
                    ->defaultCountry(config('core.default_country_code', 'GH')),

                TextInput::make('email')
                    ->label('Contact Email')
                    ->email(),

                TextInput::make('address')
                    ->label('Contact Address')
                    ->placeholder('Address of emergency contact')
                    ->columnSpanFull(),

                Toggle::make('can_make_medical_decisions')
                    ->label('Can make medical decisions')
                    ->helperText('This person has authority to make medical decisions on behalf of the patient')
                    ->default(false),
            ]);
    }

    public static function identifiersSection(): Section
    {
        return Section::make('Patient Identifiers')
            ->description('Official identification documents')
            ->collapsed()
            ->schema([
                Grid::make()->schema([
                    Select::make('type')
                        ->label('ID Type')
                        ->options(IdentifierType::class)
                        ->default(IdentifierType::NATIONAL_ID)
                        ->live(),

                    TextInput::make('value')
                        ->label('ID Number')
                        ->placeholder('Enter ID number')
                        ->rules(function (Get $get, TextInput $component) {
                            if ($get('type') == IdentifierType::NATIONAL_ID->value) {
                                $component->rules(['required', new GhanaCard]);
                            }
                        }),
                ]),

                Grid::make(3)->schema([
                    Select::make('.issuer')
                        ->label('Issuing Authority')
                        ->options([
                            'NHIA' => 'National Health Insurance Authority',
                            'GRA' => 'Ghana Revenue Authority',
                            'EC' => 'Electoral Commission',
                            'DVLA' => 'Driver and Vehicle Licensing Authority',
                            'NIA' => 'National Identification Authority',
                            'other' => 'Other',
                        ]),

                    Select::make('issuer_country')
                        ->label('Issuing Country')
                        ->default(config('core.default_country_code', 'GH'))
                        ->options(Country::pluck('name', 'iso2')?->toArray() ?? [])
                        ->preload()
                        ->searchable(),

                    DateTimePicker::make('expiry_date')
                        ->label('Expiry Date')
                        ->native(false)
                        ->displayFormat('d M Y')
                        ->minDate(now()),
                ]),

                TextEntry::make('identifier_hint')
                    ->label('Adding More Identifiers')
                    ->state('After creating the patient, you can add more identifiers from the patient profile.')
                    ->columnSpanFull(),
            ]);
    }

    public static function simpleForm(): array
    {
        return [
            Grid::make()->schema([
                TextInput::make('first_name')
                    ->label('First Name')
                    ->required()
                    ->autofocus(),

                TextInput::make('last_name')
                    ->label('Last Name')
                    ->required(),

                DateTimePicker::make('date_of_birth')
                    ->label('Date of Birth')
                    ->required()
                    ->native(false)
                    ->displayFormat('d M Y'),

                Select::make('gender')
                    ->label('Gender')
                    ->required()
                    ->options(Gender::class),

                PhoneInput::make('phone')
                    ->label('Phone Number')
                    ->required()
                    ->defaultCountry(config('core.default_country_code', 'GH')),
            ]),
        ];
    }

    public static function documentsSection(): array
    {
        return [
            Repeater::make('documents')
                ->relationship('documents')
                ->schema([
                    TextInput::make('title')
                        ->label('Document Title')
                        ->required()
                        ->placeholder('e.g., National ID Card'),

                    Select::make('document_type')
                        ->label('Document Type')
                        ->options(DocumentType::class)
                        ->required()
                        ->live(),

                    DatePicker::make('expires_at')
                        ->label('Expiry Date')
                        ->native(false)
                        ->displayFormat('d M Y')
                        ->minDate(now()),

                    Textarea::make('description')
                        ->label('Description')
                        ->placeholder('Optional notes about this document')
                        ->rows(2),

                    Textarea::make('notes')
                        ->label('Internal Notes')
                        ->placeholder('Internal notes (not visible to patient)')
                        ->rows(2),
                ])
                ->columns(2)
                ->addActionLabel('Add Document'),
        ];
    }
}
