<?php

namespace Modules\Patient\Filament\Clusters\Patient\Resources\Patients\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Modules\Patient\Enums\SchoolType;
use Ysfkaya\FilamentPhoneInput\Forms\PhoneInput;

class PatientSchoolFormSchema
{
    public static function getFields(bool $isCurrentDefault = true): array
    {
        return [
            Grid::make(3)->schema([
                Select::make('school_type')
                    ->label('School Type')
                    ->options(SchoolType::class)
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(function (Set $set, ?string $state) {
                        $set('level', null);
                        $set('hostel', null);
                        $set('hostel_room', null);
                        $set('course', null);
                        $set('course_duration', null);
                    })
                    ->required(),

                TextInput::make('school_name')
                    ->label('School Name')
                    ->required()
                    ->placeholder('e.g., Accra Academy'),

                TextInput::make('school_id')
                    ->label('School ID / Code')
                    ->placeholder('Optional'),
            ]),

            Grid::make(3)->schema([
                Select::make('level')
                    ->label('Level / Class')
                    ->options(fn (Get $get): array => self::getLevelOptions($get('school_type')))
                    ->searchable()
                    ->live(),

                TextInput::make('class_name')
                    ->label('Class Name')
                    ->placeholder('e.g., Gold Track, 100L'),

                TextInput::make('classroom')
                    ->label('Classroom')
                    ->placeholder('e.g., Room A1, Block 2'),
            ]),

            Grid::make(3)->schema([
                TextInput::make('course')
                    ->label('Course / Program')
                    ->placeholder('e.g., General Science, Nursing')
                    ->visible(fn (Get $get): bool => self::requiresCourse($get('school_type'))),

                TextInput::make('course_duration')
                    ->label('Course Duration')
                    ->placeholder('e.g., 3 years, 4 years')
                    ->visible(fn (Get $get): bool => self::requiresCourse($get('school_type'))),

                TextInput::make('year_of_study')
                    ->label('Year of Study')
                    ->placeholder('e.g., Year 1, Final Year'),
            ]),

            Grid::make(2)->schema([
                TextInput::make('hostel')
                    ->label('Hostel Name')
                    ->placeholder('e.g., Sarbah Hall')
                    ->visible(fn (Get $get): bool => self::requiresHostel($get('school_type'))),

                TextInput::make('hostel_room')
                    ->label('Hostel Room')
                    ->placeholder('e.g., Room 12, Block C')
                    ->visible(fn (Get $get): bool => self::requiresHostel($get('school_type'))),
            ]),

            Grid::make(3)->schema([
                TextInput::make('school_address')
                    ->label('School Address')
                    ->placeholder('School location'),

                PhoneInput::make('school_phone')
                    ->label('School Phone')
                    ->defaultCountry(config('core.default_country_code', 'GH')),

                TextInput::make('school_email')
                    ->label('School Email')
                    ->email()
                    ->placeholder('school@example.com'),
            ]),

            Grid::make(3)->schema([
                DatePicker::make('admission_date')
                    ->label('Admission Date')
                    ->native(false)
                    ->displayFormat('d M Y'),

                DatePicker::make('graduation_date')
                    ->label('Expected Graduation Date')
                    ->native(false)
                    ->displayFormat('d M Y'),
            ]),

            Textarea::make('notes')
                ->label('School Notes')
                ->placeholder('Additional notes about the student\'s enrollment')
                ->columnSpanFull()
                ->rows(2),

            Hidden::make('is_current')->default(fn (): bool => $isCurrentDefault),
            Hidden::make('is_active')->default(true),
            Hidden::make('created_by')->default(fn (): ?int => auth()->id()),
        ];
    }

    protected static function getLevelOptions(?string $schoolType): array
    {
        if (! $schoolType) {
            return [];
        }

        $type = SchoolType::tryFrom($schoolType);

        return $type ? array_combine($type->getClassLevels(), $type->getClassLevels()) : [];
    }

    protected static function requiresHostel(?string $schoolType): bool
    {
        $type = SchoolType::tryFrom($schoolType);

        return $type?->requiresHostel() ?? false;
    }

    protected static function requiresCourse(?string $schoolType): bool
    {
        $type = SchoolType::tryFrom($schoolType);

        return $type?->requiresCourse() ?? false;
    }
}
