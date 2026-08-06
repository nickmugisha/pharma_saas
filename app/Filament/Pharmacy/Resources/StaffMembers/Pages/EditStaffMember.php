<?php

namespace App\Filament\Pharmacy\Resources\StaffMembers\Pages;

use App\Filament\Pharmacy\Resources\StaffMembers\StaffMemberResource;
use App\Models\User;
use App\Services\StaffRecruitmentService;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditStaffMember extends EditRecord
{
    protected static string $resource = StaffMemberResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        abort_unless($this->record instanceof User, 404);

        $data['staff_role'] = app(StaffRecruitmentService::class)
            ->roleName($this->record);

        $data['password'] = null;
        $data['password_confirmation'] = null;
        $data['reason'] = null;

        return $data;
    }

    protected function handleRecordUpdate(
        Model $record,
        array $data,
    ): Model {
        abort_unless($record instanceof User, 404);

        $actor = auth()->user();

        abort_unless($actor instanceof User, 403);

        return app(StaffRecruitmentService::class)
            ->update($actor, $record, $data);
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Employee access updated successfully';
    }
}
