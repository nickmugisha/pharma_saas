<?php

namespace App\Filament\Pharmacy\Resources\Customers\Pages;

use App\Filament\Pharmacy\Resources\Customers\CustomerResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCustomer extends ViewRecord
{
    protected static string $resource =
        CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->visible(
                    fn (): bool =>
                        CustomerResource::canEdit(
                            $this->record,
                        ),
                ),
        ];
    }
}