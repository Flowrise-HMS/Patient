<?php

namespace Modules\Patient\Filament\Clusters\Patient\Resources\Patients\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Modules\Patient\Models\PatientSchool;

class PatientSchoolInfolist
{
    public static function getEntries(): array
    {
        return [
            TextEntry::make('school_name')
                ->label('School')
                ->weight('bold')
                ->size('lg'),

            TextEntry::make('school_type')
                ->label('Type')
                ->badge()
                ->color(fn (string $state): string => match ($state) {
                    'nursery', 'creche', 'kindergarten' => 'warning',
                    'primary' => 'info',
                    'junior_high', 'senior_high' => 'primary',
                    'vocational' => 'secondary',
                    'tertiary', 'university' => 'success',
                    'professional' => 'danger',
                    default => 'gray',
                })
                ->formatStateUsing(fn (string $state): string => str($state)->replace('_', ' ')->title()),

            TextEntry::make('level')
                ->label('Level'),

            TextEntry::make('class_name')
                ->label('Class'),

            TextEntry::make('course')
                ->label('Course')
                ->visible(fn (PatientSchool $record): bool => (bool) $record->course),

            TextEntry::make('year_of_study')
                ->label('Year of Study'),

            TextEntry::make('hostel')
                ->label('Hostel')
                ->visible(fn (PatientSchool $record): bool => (bool) $record->hostel),

            TextEntry::make('hostel_room')
                ->label('Room')
                ->visible(fn (PatientSchool $record): bool => (bool) $record->hostel_room),

            TextEntry::make('admission_date')
                ->label('Admitted')
                ->date(),

            TextEntry::make('graduation_date')
                ->label('Graduation')
                ->date(),

            TextEntry::make('school_phone')
                ->label('School Phone'),

            TextEntry::make('school_email')
                ->label('School Email'),

            TextEntry::make('is_current')
                ->label('Current')
                ->badge()
                ->color(fn (bool $state): string => $state ? 'success' : 'gray')
                ->formatStateUsing(fn (bool $state): string => $state ? 'Current' : 'Past'),

            TextEntry::make('notes')
                ->label('Notes')
                ->visible(fn (PatientSchool $record): bool => (bool) $record->notes),
        ];
    }

    public static function getCurrentSchoolSection(): Section
    {
        return Section::make('Current School')
            ->visible(fn ($record): bool => $record && $record->schools()->exists())
            ->schema([
                TextEntry::make('school_summary')
                    ->label('')
                    ->state(function ($record): string {
                        $school = $record->currentSchool->first() ?? $record->schools()->latest()->first();
                        if (! $school) {
                            return '';
                        }

                        return $school->display_name;
                    })
                    ->weight('bold')
                    ->size('lg'),

                ...static::getCompactEntries(),
            ]);
    }

    protected static function getCompactEntries(): array
    {
        return [
            TextEntry::make('school_type_display')
                ->label('Type')
                ->state(fn ($record) => $record->currentSchool->first()?->school_type ?? $record->schools()->latest()->first()?->school_type)
                ->badge()
                ->color(fn (?string $state): string => match ($state) {
                    'nursery', 'creche', 'kindergarten' => 'warning',
                    'primary' => 'info',
                    'junior_high', 'senior_high' => 'primary',
                    'vocational' => 'secondary',
                    'tertiary', 'university' => 'success',
                    'professional' => 'danger',
                    default => 'gray',
                })
                ->formatStateUsing(fn (?string $state): string => $state ? str($state)->replace('_', ' ')->title() : '-'),

            TextEntry::make('school_level_display')
                ->label('Level')
                ->state(fn ($record) => $record->currentSchool->first()?->level),

            TextEntry::make('school_course_display')
                ->label('Course')
                ->state(fn ($record) => $record->currentSchool->first()?->course)
                ->visible(fn ($record): bool => (bool) ($record->currentSchool->first()?->course)),
        ];
    }
}
