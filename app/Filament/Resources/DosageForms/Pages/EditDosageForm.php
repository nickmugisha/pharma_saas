<?php

namespace App\Filament\Resources\DosageForms\Pages;

use App\Filament\Resources\DosageForms\DosageFormResource;
use Filament\Resources\Pages\EditRecord;

class EditDosageForm extends EditRecord
{
    protected static string $resource = DosageFormResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}