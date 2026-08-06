<?php

namespace App\Filament\Pharmacy\Resources\MarketplaceOrders\Pages;

use App\Actions\Marketplace\RecordMarketplaceOrderEvent;
use App\Actions\Marketplace\RefundMarketplaceOrder;
use App\Actions\Marketplace\ReleaseMarketplaceOrderStock;
use App\Actions\Marketplace\ReviewMarketplaceOrder;
use App\Filament\Pharmacy\Resources\MarketplaceOrders\MarketplaceOrderResource;
use App\Models\MarketplaceOrder;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewMarketplaceOrder extends ViewRecord
{
    protected static string $resource = MarketplaceOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approvePrescription')
                ->label('Approve prescription')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (): bool =>
                    $this->record->status === MarketplaceOrder::STATUS_AWAITING_REVIEW
                    && (auth()->user()?->can('marketplace.prescriptions.review') ?? false))
                ->action(function (): void {
                    app(ReviewMarketplaceOrder::class)->approve(
                        $this->currentUser(),
                        $this->record,
                    );

                    Notification::make()
                        ->success()
                        ->title('Prescription approved and stock reserved')
                        ->send();

                    $this->redirectToRecord();
                }),

            Action::make('rejectPrescription')
                ->label('Reject prescription')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (): bool =>
                    $this->record->status === MarketplaceOrder::STATUS_AWAITING_REVIEW
                    && (auth()->user()?->can('marketplace.prescriptions.review') ?? false))
                ->schema([
                    Textarea::make('reason')
                        ->required()
                        ->minLength(5)
                        ->maxLength(2000)
                        ->rows(4),
                ])
                ->action(function (array $data): void {
                    app(ReviewMarketplaceOrder::class)->reject(
                        $this->currentUser(),
                        $this->record,
                        $data['reason'],
                    );

                    Notification::make()
                        ->success()
                        ->title('Prescription rejected and order cancelled')
                        ->send();

                    $this->redirectToRecord();
                }),

            Action::make('refundOrder')
                ->label('Cancel and refund')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('danger')
                ->visible(fn (): bool =>
                    $this->record->status === MarketplaceOrder::STATUS_CONFIRMED
                    && $this->record->payment_status === MarketplaceOrder::PAYMENT_PAID
                    && (auth()->user()?->can('marketplace.orders.manage') ?? false))
                ->schema([
                    Textarea::make('reason')
                        ->label('Cancellation and refund reason')
                        ->required()
                        ->minLength(10)
                        ->maxLength(2000)
                        ->rows(4),
                ])
                ->requiresConfirmation()
                ->modalDescription(
                    'This restores converted stock reservations and credits the client wallet.',
                )
                ->action(function (array $data): void {
                    app(RefundMarketplaceOrder::class)->handle(
                        actor: $this->currentUser(),
                        order: $this->record,
                        reason: $data['reason'],
                    );

                    Notification::make()
                        ->success()
                        ->title('Order cancelled and wallet refunded')
                        ->send();

                    $this->redirectToRecord();
                }),

            Action::make('cancelOrder')
                ->label('Cancel order')
                ->icon('heroicon-o-no-symbol')
                ->color('danger')
                ->visible(fn (): bool =>
                    in_array($this->record->status, [
                        MarketplaceOrder::STATUS_AWAITING_REVIEW,
                        MarketplaceOrder::STATUS_AWAITING_PAYMENT,
                    ], true)
                    && (auth()->user()?->can('marketplace.orders.manage') ?? false))
                ->schema([
                    Textarea::make('reason')
                        ->required()
                        ->minLength(5)
                        ->maxLength(2000)
                        ->rows(4),
                ])
                ->action(function (array $data): void {
                    $user = $this->currentUser();

                    if ($this->record->stockReservations()->where('status', 'held')->exists()) {
                        app(ReleaseMarketplaceOrderStock::class)->handle(
                            order: $this->record,
                            reason: $data['reason'],
                            actor: $user,
                        );
                    } else {
                        $this->record->forceFill([
                            'status' => MarketplaceOrder::STATUS_CANCELLED,
                            'cancelled_at' => now(),
                            'cancellation_reason' => $data['reason'],
                        ])->save();

                        app(RecordMarketplaceOrderEvent::class)->handle(
                            order: $this->record,
                            eventType: 'order_cancelled',
                            title: 'Order cancelled by pharmacy',
                            description: $data['reason'],
                            actor: $user,
                        );
                    }

                    Notification::make()
                        ->success()
                        ->title('Order cancelled')
                        ->send();

                    $this->redirectToRecord();
                }),
        ];
    }

    private function currentUser(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);
        return $user;
    }

    private function redirectToRecord(): void
    {
        $this->redirect(MarketplaceOrderResource::getUrl('view', [
            'record' => $this->record,
        ]));
    }
}
