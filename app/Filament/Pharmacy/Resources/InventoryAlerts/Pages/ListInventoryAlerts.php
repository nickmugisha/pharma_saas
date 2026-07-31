<?php

namespace App\Filament\Pharmacy\Resources\InventoryAlerts\Pages;

use App\Actions\Stock\SyncInventoryAlerts;
use App\Filament\Pharmacy\Resources\InventoryAlerts\InventoryAlertResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListInventoryAlerts extends ListRecords
{
    protected static string $resource =
        InventoryAlertResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('syncAlerts')
                ->label('Sync alerts')
                ->icon('heroicon-o-arrow-path')
                ->visible(
                    fn (): bool =>
                        auth()->user()?->can('stock.manage')
                        ?? false,
                )
                ->action(function (): void {
                    $count = app(SyncInventoryAlerts::class)
                        ->handle(
                            pharmacyId:
                                auth()->user()?->pharmacy_id,
                        );

                    Notification::make()
                        ->title('Inventory alerts synchronized')
                        ->body(
                            "{$count} branch medicine setting(s) checked."
                        )
                        ->success()
                        ->send();
                }),
        ];
    }
}