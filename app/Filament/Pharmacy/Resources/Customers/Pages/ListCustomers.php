<?php

namespace App\Filament\Pharmacy\Resources\Customers\Pages;

use App\Filament\Pharmacy\Resources\Customers\CustomerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCustomers extends ListRecords
{
    protected static string $resource =
        CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Register customer')
                ->icon('heroicon-o-user-plus'),
        ];
    }
}