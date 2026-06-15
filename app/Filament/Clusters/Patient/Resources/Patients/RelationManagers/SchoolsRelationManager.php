<?php

namespace Modules\Patient\Filament\Clusters\Patient\Resources\Patients\RelationManagers;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Modules\Patient\Classes\Services\PatientSchoolService;
use Modules\Patient\Enums\SchoolType;
use Modules\Patient\Filament\Clusters\Patient\Resources\Patients\Schemas\PatientSchoolFormSchema;
use Modules\Patient\Filament\Clusters\Patient\Resources\Patients\Schemas\PatientSchoolInfolist;

class SchoolsRelationManager extends RelationManager
{
    protected static string $relationship = 'schools';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components(PatientSchoolFormSchema::getFields(isCurrentDefault: false));
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components(PatientSchoolInfolist::getEntries());
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('school_name')
            ->columns([
                TextColumn::make('school_name')
                    ->label('School')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('school_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => SchoolType::tryFrom($state)?->getColor() ?? 'gray')
                    ->formatStateUsing(fn (string $state): string => SchoolType::tryFrom($state)?->getLabel() ?? $state),

                TextColumn::make('level')
                    ->label('Level'),

                TextColumn::make('class_name')
                    ->label('Class'),

                TextColumn::make('course')
                    ->label('Course'),

                IconColumn::make('is_current')
                    ->label('Current')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('gray'),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('danger'),

                TextColumn::make('admission_date')
                    ->label('Admitted')
                    ->date(),

                TextColumn::make('graduation_date')
                    ->label('Graduated')
                    ->date(),
            ])
            ->filters([
                TrashedFilter::make(),
                SelectFilter::make('school_type')
                    ->label('School Type')
                    ->options(SchoolType::class),
                TernaryFilter::make('is_current')
                    ->label('Current School')
                    ->placeholder('All')
                    ->trueLabel('Current Only')
                    ->falseLabel('Past Only'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['is_current'] ??= false;

                        return $data;
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('setAsCurrent')
                    ->label('Set as Current')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->action(function ($record) {
                        app(PatientSchoolService::class)->setAsCurrent($record);
                    })
                    ->visible(fn ($record): bool => ! $record->is_current),
                Action::make('complete')
                    ->label('Complete / Graduate')
                    ->icon('heroicon-o-academic-cap')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        app(PatientSchoolService::class)->completeCurrentSchool($record->patient, now());
                    })
                    ->visible(fn ($record): bool => $record->is_current),
                ActionGroup::make([
                    DeleteAction::make(),
                    ForceDeleteAction::make(),
                    RestoreAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->withoutGlobalScopes([
                    SoftDeletingScope::class,
                ]));
    }
}
