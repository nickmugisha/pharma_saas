<?php

namespace App\Filament\Pharmacy\Resources\SupplierPayments\Pages;

use App\Filament\Pharmacy\Resources\SupplierPayments\SupplierPaymentResource;
use App\Models\SupplierPayment;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;

class EditSupplierPayment extends EditRecord
{
    protected static string $resource = SupplierPaymentResource::class;

    protected function getFormActions(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('void')
                ->label('Void payment')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('danger')
                ->visible(
                    fn (): bool =>
                        $this->record->status === 'completed',
                )
                ->requiresConfirmation()
                ->schema([
                    Textarea::make('void_reason')
                        ->label('Void reason')
                        ->required()
                        ->rows(3),
                ])
                ->action(function (array $data): mixed {
                    DB::transaction(function () use ($data): void {
                        $payment = SupplierPayment::query()
                            ->whereKey($this->record->id)
                            ->where(
                                'pharmacy_id',
                                auth()->user()?->pharmacy_id ?? 0,
                            )
                            ->lockForUpdate()
                            ->firstOrFail();

                        abort_unless(
                            $payment->status === 'completed',
                            422,
                        );

                        $payment->forceFill([
                            'status' => 'voided',
                            'voided_by_user_id' => auth()->id(),
                            'voided_at' => now(),
                            'void_reason' => $data['void_reason'],
                        ])->save();
                    });

                    Notification::make()
                        ->title('Supplier payment voided')
                        ->success()
                        ->send();

                    return $this->redirect(
                        SupplierPaymentResource::getUrl('edit', [
                            'record' => $this->record,
                        ]),
                    );
                }),
        ];
    }
}