<?php

namespace App\Filament\Pharmacy\Resources\StaffMembers\Pages;

use App\Filament\Pharmacy\Resources\StaffMembers\StaffMemberResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStaffMembers extends ListRecords
{
    protected static string $resource = StaffMemberResource::class;

    protected static ?string $title = 'Staff & Access';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Recruit employee')
                ->icon('heroicon-o-user-plus'),
        ];
    }
}
