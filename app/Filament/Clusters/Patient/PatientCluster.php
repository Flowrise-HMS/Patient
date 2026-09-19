<?php

namespace Modules\Patient\Filament\Clusters\Patient;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;
use Modules\Core\Enums\SidebarGroup;

class PatientCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|\UnitEnum|null $navigationGroup = SidebarGroup::PatientCare;

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Patients';

    protected static bool $shouldRegisterSubNavigation = false;
}
