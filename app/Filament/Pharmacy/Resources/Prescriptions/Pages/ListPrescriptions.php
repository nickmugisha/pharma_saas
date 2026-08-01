<?php

namespace App\Filament\Pharmacy\Resources\Prescriptions\Pages;

use App\Filament\Pharmacy\Resources\Prescriptions\PrescriptionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPrescriptions extends ListRecords
{
    protected static string $resource =
        PrescriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Register prescription')
                ->icon('heroicon-o-document-plus'),
        ];
    }
}