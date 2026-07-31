<?php

namespace App\Filament\Resources\Molecules\Pages;

use App\Filament\Resources\Molecules\MoleculeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMolecules extends ListRecords
{
    protected static string $resource = MoleculeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
