<?php

namespace App\Filament\Pharmacy\Resources\SupplierPayments\Pages;

use App\Actions\Purchasing\RecordSupplierPayment;
use App\Filament\Pharmacy\Resources\SupplierPayments\SupplierPaymentResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateSupplierPayment extends CreateRecord
{
    protected static string $resource = SupplierPaymentResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(RecordSupplierPayment::class)
            ->handle(auth()->user(), $data);
    }
}