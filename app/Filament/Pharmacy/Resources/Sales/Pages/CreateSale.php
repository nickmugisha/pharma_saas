<?php

namespace App\Filament\Pharmacy\Resources\Sales\Pages;

use App\Actions\Sales\CompletePosSale;
use App\Filament\Pharmacy\Resources\Sales\SaleResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateSale extends CreateRecord
{
    protected static string $resource =
        SaleResource::class;

    protected static ?string $title =
        'New POS Sale';

    protected function handleRecordCreation(
        array $data,
    ): Model {
        $items = $data['items'] ?? [];
        $payments = $data['payments'] ?? [];

        return app(CompletePosSale::class)->handle(
            user: auth()->user(),
            lines: $items,
            payments: $payments,
            saleData: [
                'pharmacy_branch_id' =>
                    $data['pharmacy_branch_id'] ?? null,

                'customer_name' =>
                    $data['customer_name'] ?? null,

                'customer_phone' =>
                    $data['customer_phone'] ?? null,

                'notes' =>
                    $data['notes'] ?? null,
            ],
        );
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

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'POS sale completed successfully';
    }
}