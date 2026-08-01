<?php

namespace App\Filament\Pharmacy\Resources\Prescriptions\Pages;

use App\Actions\Prescriptions\SavePrescriptionDraft;
use App\Filament\Pharmacy\Resources\Prescriptions\PrescriptionResource;
use App\Models\Prescription;
use App\Models\User;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditPrescription extends EditRecord
{
    protected static string $resource =
        PrescriptionResource::class;

    protected function mutateFormDataBeforeFill(
        array $data,
    ): array {
        /** @var Prescription $prescription */
        $prescription = $this->record;

        $prescription->loadMissing([
            'items',
            'attachments',
        ]);

        $data['items'] = $prescription
            ->items
            ->map(
                fn ($item): array => [
                    'pharmacy_medicine_id' =>
                        $item->pharmacy_medicine_id,
                    'prescribed_name' =>
                        $item->prescribed_name,
                    'strength' => $item->strength,
                    'dosage_form' =>
                        $item->dosage_form,
                    'dosage' => $item->dosage,
                    'frequency' => $item->frequency,
                    'duration' => $item->duration,
                    'quantity_prescribed' =>
                        $item->quantity_prescribed,
                    'substitution_allowed' =>
                        $item->substitution_allowed,
                    'instructions' =>
                        $item->instructions,
                ],
            )
            ->values()
            ->all();

        $data['new_attachment_paths'] = [];
        $data['attachment_original_names'] = [];

        return $data;
    }

    protected function handleRecordUpdate(
        Model $record,
        array $data,
    ): Model {
        abort_unless(
            $record instanceof Prescription,
            404,
        );

        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        return app(SavePrescriptionDraft::class)
            ->update(
                actor: $user,
                prescription: $record,
                data: $data,
            );
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
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

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Prescription draft updated';
    }
}