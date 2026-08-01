<?php

namespace App\Filament\Pharmacy\Resources\Sales\Pages;

use App\Actions\Sales\VoidCompletedSale;
use App\Filament\Pharmacy\Resources\Sales\SaleResource;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewSale extends ViewRecord
{
    protected static string $resource =
        SaleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('voidSale')
                ->label('Void sale')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(
                    fn (): bool =>
                        $this->record->status === 'completed'
                        && (
                            auth()->user()?->can('sales.void')
                            ?? false
                        ),
                )
                ->requiresConfirmation()
                ->modalHeading('Void POS sale')
                ->modalDescription(
                    'This action will restore the exact medicine batches, reverse completed payments and permanently mark the sale as voided.'
                )
                ->modalSubmitActionLabel(
                    'Void sale and restore stock'
                )
                ->schema([
                    Textarea::make('reason')
                        ->label('Reason for voiding')
                        ->required()
                        ->minLength(10)
                        ->maxLength(1000)
                        ->rows(4)
                        ->placeholder(
                            'Explain why this completed sale must be voided.'
                        ),
                ])
                ->action(function (array $data): void {
                    $user = auth()->user();

                    abort_unless($user, 403);

                    app(VoidCompletedSale::class)->handle(
                        user: $user,
                        sale: $this->record,
                        reason: $data['reason'],
                    );

                    Notification::make()
                        ->title('Sale voided successfully')
                        ->body(
                            'Stock was restored and completed payments were reversed.'
                        )
                        ->success()
                        ->send();

                    $this->redirect(
                        SaleResource::getUrl(
                            'view',
                            [
                                'record' => $this->record,
                            ],
                        ),
                    );
                }),
        ];
    }
}