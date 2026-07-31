<?php

namespace App\Filament\Resources\Molecules\Pages;

use App\Filament\Resources\Molecules\MoleculeResource;
use Filament\Resources\Pages\EditRecord;

class EditMolecule extends EditRecord
{
    protected static string $resource = MoleculeResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}