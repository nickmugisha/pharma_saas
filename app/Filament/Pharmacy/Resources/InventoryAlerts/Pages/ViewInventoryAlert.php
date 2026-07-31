<?php

namespace App\Filament\Pharmacy\Resources\InventoryAlerts\Pages;

use App\Filament\Pharmacy\Resources\InventoryAlerts\InventoryAlertResource;
use App\Models\InventoryAlert;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\DB;

class ViewInventoryAlert extends ViewRecord
{
    protected static string $resource =
        InventoryAlertResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('acknowledge')
                ->label('Acknowledge')
                ->icon('heroicon-o-eye')
                ->color('warning')
                ->visible(
                    fn (): bool =>
                        $this->record->status === 'open'
                        && (
                            auth()->user()?->can('stock.manage')
                            ?? false
                        ),
                )
                ->requiresConfirmation()
                ->action(function (): void {
                    DB::transaction(function (): void {
                        $alert = InventoryAlert::query()
                            ->whereKey($this->record->id)
                            ->where(
                                'pharmacy_id',
                                auth()->user()?->pharmacy_id ?? 0,
                            )
                            ->lockForUpdate()
                            ->firstOrFail();

                        $alert->acknowledge(auth()->user());
                    });

                    $this->record->refresh();

                    Notification::make()
                        ->title('Alert acknowledged')
                        ->success()
                        ->send();
                }),

            Action::make('resolve')
                ->label('Resolve alert')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(
                    fn (): bool =>
                        $this->record->status !== 'resolved'
                        && (
                            auth()->user()?->can('stock.manage')
                            ?? false
                        ),
                )
                ->requiresConfirmation()
                ->modalDescription(
                    'The alert will reopen automatically when synchronization detects that the condition still exists.'
                )
                ->action(function (): void {
                    DB::transaction(function (): void {
                        $alert = InventoryAlert::query()
                            ->whereKey($this->record->id)
                            ->where(
                                'pharmacy_id',
                                auth()->user()?->pharmacy_id ?? 0,
                            )
                            ->lockForUpdate()
                            ->firstOrFail();

                        $alert->resolve(auth()->user());
                    });

                    $this->record->refresh();

                    Notification::make()
                        ->title('Alert resolved')
                        ->success()
                        ->send();
                }),
        ];
    }
}