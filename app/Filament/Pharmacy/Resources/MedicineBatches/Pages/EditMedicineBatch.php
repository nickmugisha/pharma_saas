<?php

namespace App\Filament\Pharmacy\Resources\MedicineBatches\Pages;

use App\Filament\Pharmacy\Resources\MedicineBatches\MedicineBatchResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMedicineBatch extends EditRecord
{
    protected static string $resource = MedicineBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
