<?php

namespace App\Filament\Resources\DosageForms\Pages;

use App\Filament\Resources\DosageForms\DosageFormResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDosageForms extends ListRecords
{
    protected static string $resource = DosageFormResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
