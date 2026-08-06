<?php

namespace App\Filament\Pharmacy\Resources\StaffMembers\Pages;

use App\Filament\Pharmacy\Resources\StaffMembers\StaffMemberResource;
use App\Models\User;
use App\Services\StaffRecruitmentService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateStaffMember extends CreateRecord
{
    protected static string $resource = StaffMemberResource::class;

    protected static ?string $title = 'Recruit employee';

    protected static bool $canCreateAnother = false;

    protected function handleRecordCreation(array $data): Model
    {
        $actor = auth()->user();

        abort_unless($actor instanceof User, 403);

        return app(StaffRecruitmentService::class)
            ->create($actor, $data);
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', [
            'record' => $this->record,
        ]);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Employee recruited successfully';
    }
}
