<?php

namespace App\Filament\Pharmacy\Resources\Prescriptions\Pages;

use App\Actions\Prescriptions\SavePrescriptionDraft;
use App\Filament\Pharmacy\Resources\Prescriptions\PrescriptionResource;
use App\Models\Prescription;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePrescription extends CreateRecord
{
    protected static string $resource =
        PrescriptionResource::class;

    protected static ?string $title =
        'Register Prescription';

    protected static bool $canCreateAnother = false;

    protected function handleRecordCreation(
        array $data,
    ): Model {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        return app(SavePrescriptionDraft::class)
            ->create(
                actor: $user,
                data: $data,
            );
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl(
            'view',
            [
                'record' => $this->record,
            ],
        );
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Prescription draft created';
    }
}