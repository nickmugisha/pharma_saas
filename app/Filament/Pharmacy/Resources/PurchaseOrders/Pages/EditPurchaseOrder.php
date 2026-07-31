<?php

namespace App\Filament\Pharmacy\Resources\PurchaseOrders\Pages;

use App\Filament\Pharmacy\Resources\PurchaseOrders\PurchaseOrderResource;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditPurchaseOrder extends EditRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        abort_unless(
            (int) $this->record->pharmacy_id
                === (int) auth()->user()?->pharmacy_id,
            403,
        );

        if ($this->record->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' =>
                    'Only draft purchase orders can be modified.',
            ]);
        }

        $data['status'] = 'draft';
        $data['currency'] = 'BIF';

        return $data;
    }

    protected function afterSave(): void
    {
        $this->record->recalculateTotals();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('submit')
                ->label('Submit order')
                ->icon('heroicon-o-paper-airplane')
                ->color('warning')
                ->visible(
                    fn (): bool =>
                        $this->record->status === 'draft',
                )
                ->requiresConfirmation()
                ->action(function (): mixed {
                    $this->record->recalculateTotals();

                    if (! $this->record->items()->exists()) {
                        throw ValidationException::withMessages([
                            'items' =>
                                'Add at least one medicine before submitting.',
                        ]);
                    }

                    $this->record->update([
                        'status' => 'submitted',
                    ]);

                    Notification::make()
                        ->title('Purchase order submitted')
                        ->success()
                        ->send();

                    return $this->redirect(
                        PurchaseOrderResource::getUrl('edit', [
                            'record' => $this->record,
                        ]),
                    );
                }),

            Action::make('approve')
                ->label('Approve order')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(
                    fn (): bool =>
                        $this->record->status === 'submitted',
                )
                ->requiresConfirmation()
                ->action(function (): mixed {
                    $this->record->forceFill([
                        'status' => 'approved',
                        'approved_by_user_id' => auth()->id(),
                    ])->save();

                    Notification::make()
                        ->title('Purchase order approved')
                        ->success()
                        ->send();

                    return $this->redirect(
                        PurchaseOrderResource::getUrl('edit', [
                            'record' => $this->record,
                        ]),
                    );
                }),

            Action::make('cancel')
                ->label('Cancel order')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(
                    fn (): bool =>
                        in_array(
                            $this->record->status,
                            ['draft', 'submitted'],
                            true,
                        ),
                )
                ->requiresConfirmation()
                ->schema([
                    Textarea::make('reason')
                        ->label('Cancellation reason')
                        ->required()
                        ->rows(3),
                ])
                ->action(function (array $data): mixed {
                    $existingNotes = trim(
                        (string) $this->record->notes,
                    );

                    $cancellationNote =
                        'Cancellation reason: '.$data['reason'];

                    $this->record->forceFill([
                        'status' => 'cancelled',
                        'notes' => $existingNotes === ''
                            ? $cancellationNote
                            : $existingNotes.PHP_EOL.$cancellationNote,
                    ])->save();

                    Notification::make()
                        ->title('Purchase order cancelled')
                        ->success()
                        ->send();

                    return $this->redirect(
                        PurchaseOrderResource::getUrl('edit', [
                            'record' => $this->record,
                        ]),
                    );
                }),
        ];
    }

    protected function getFormActions(): array
    {
        if ($this->record->status !== 'draft') {
            return [];
        }

        return parent::getFormActions();
    }
}