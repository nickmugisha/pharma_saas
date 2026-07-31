<?php

namespace App\Filament\Pharmacy\Resources\PurchaseOrders\Pages;

use App\Filament\Pharmacy\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Actions\Stock\ReceivePurchaseOrder;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;

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
                Action::make('receiveStock')
    ->label('Receive stock')
    ->icon('heroicon-o-arrow-down-tray')
    ->color('success')
    ->visible(
        fn (): bool => in_array(
            $this->record->status,
            ['approved', 'partially_received'],
            true,
        ),
    )
    ->modalHeading(
        fn (): string =>
            "Receive purchase order {$this->record->order_number}",
    )
    ->modalDescription(
        'Record the quantities, batch numbers and expiry dates physically received.'
    )
    ->modalSubmitActionLabel('Complete receipt')
    ->schema([
        Textarea::make('notes')
            ->label('Receipt notes')
            ->rows(2)
            ->columnSpanFull(),

        Repeater::make('items')
            ->label('Received medicines')
            ->minItems(1)
            ->defaultItems(1)
            ->addActionLabel('Add received batch')
            ->columnSpanFull()
            ->columns([
                'default' => 1,
                'md' => 2,
            ])
            ->schema([
                Select::make('purchase_order_item_id')
                    ->label('Ordered medicine')
                    ->options(function (): array {
                        return $this->record
                            ->items()
                            ->with('pharmacyMedicine.medicine')
                            ->get()
                            ->filter(function ($item): bool {
                                return (float) $item->quantity_received
                                    < (float) $item->quantity_ordered;
                            })
                            ->mapWithKeys(function ($item): array {
                                $medicine =
                                    $item->pharmacyMedicine?->medicine;

                                $name = $medicine?->brand_name
                                    ?? $medicine?->generic_name
                                    ?? 'Medicine';

                                $remaining = round(
                                    (float) $item->quantity_ordered
                                    - (float) $item->quantity_received,
                                    3,
                                );

                                return [
                                    $item->id => sprintf(
                                        '%s — Remaining: %s',
                                        $name,
                                        number_format($remaining, 3),
                                    ),
                                ];
                            })
                            ->all();
                    })
                    ->searchable()
                    ->required()
                    ->columnSpanFull(),

                TextInput::make('batch_number')
                    ->label('Batch number')
                    ->required()
                    ->maxLength(100),

                TextInput::make('quantity_received')
                    ->label('Quantity received')
                    ->numeric()
                    ->minValue(0.001)
                    ->step(0.001)
                    ->required(),

                DatePicker::make('manufacturing_date')
                    ->label('Manufacturing date')
                    ->maxDate(today()),

                DatePicker::make('expiry_date')
                    ->label('Expiry date')
                    ->minDate(today()->addDay())
                    ->required(),

                Textarea::make('notes')
                    ->label('Batch notes')
                    ->rows(2)
                    ->columnSpanFull(),
            ]),
    ])
    ->action(function (array $data): void {
        $receipt = app(ReceivePurchaseOrder::class)->handle(
            auth()->user(),
            $this->record->id,
            $data['items'],
            $data['notes'] ?? null,
        );

        $this->record->refresh();

        Notification::make()
            ->title('Stock received successfully')
            ->body(
                "Receipt {$receipt->receipt_number} was completed."
            )
            ->success()
            ->send();
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