<?php

namespace App\Filament\Pharmacy\Resources\Sales\Pages;

use App\Filament\Pharmacy\Resources\Sales\SaleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSales extends ListRecords
{
    protected static string $resource =
        SaleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('New POS sale')
                ->icon('heroicon-o-plus'),
        ];
    }
}