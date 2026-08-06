<?php

namespace App\Filament\Resources\ClientWallets\Pages;

use App\Actions\Wallet\PostWalletTransaction;
use App\Actions\Wallet\ReverseWalletTransaction;
use App\Filament\Resources\ClientWallets\ClientWalletResource;
use App\Models\ClientWallet;
use App\Models\WalletTransaction;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Str;

class ViewClientWallet extends ViewRecord
{
    protected static string $resource = ClientWalletResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('manualAdjustment')
                ->label('Post audited adjustment')
                ->icon('heroicon-o-arrows-right-left')
                ->color('primary')
                ->visible(fn (): bool =>
                    (auth()->user()?->can('wallets.manage') ?? false)
                    && $this->record->status === 'active')
                ->modalHeading('Post an immutable wallet adjustment')
                ->modalDescription(
                    'The balance is never edited directly. This action creates a permanent credit or debit ledger entry.',
                )
                ->schema([
                    Select::make('direction')
                        ->label('Adjustment direction')
                        ->options([
                            WalletTransaction::DIRECTION_CREDIT => 'Credit wallet',
                            WalletTransaction::DIRECTION_DEBIT => 'Debit wallet',
                        ])
                        ->required(),
                    TextInput::make('amount')
                        ->label('Amount')
                        ->numeric()
                        ->minValue(0.01)
                        ->step(0.01)
                        ->suffix('BIF')
                        ->required(),
                    Textarea::make('reason')
                        ->label('Business reason')
                        ->helperText('This explanation becomes part of the permanent ledger.')
                        ->required()
                        ->minLength(10)
                        ->maxLength(2000)
                        ->rows(4),
                ])
                ->action(function (array $data): void {
                    $actor = auth()->user();
                    abort_unless($actor, 403);

                    app(PostWalletTransaction::class)->handle(
                        wallet: $this->record,
                        direction: $data['direction'],
                        amount: (float) $data['amount'],
                        type: WalletTransaction::TYPE_MANUAL_ADJUSTMENT,
                        description: trim($data['reason']),
                        actor: $actor,
                        metadata: [
                            'channel' => 'super_admin_wallet_profile',
                            'reason' => trim($data['reason']),
                        ],
                        idempotencyKey: 'manual-adjustment:'.Str::uuid(),
                    );

                    Notification::make()
                        ->success()
                        ->title('Wallet adjustment recorded')
                        ->body('A permanent ledger entry was created. The balance was recalculated automatically.')
                        ->send();

                    $this->redirectToWallet();
                }),

            Action::make('suspendWallet')
                ->label('Suspend wallet')
                ->icon('heroicon-o-lock-closed')
                ->color('danger')
                ->visible(fn (): bool =>
                    (auth()->user()?->can('wallets.manage') ?? false)
                    && $this->record->status === 'active')
                ->schema([
                    Textarea::make('reason')
                        ->label('Suspension reason')
                        ->required()
                        ->minLength(10)
                        ->maxLength(2000)
                        ->rows(4),
                ])
                ->requiresConfirmation()
                ->action(function (array $data): void {
                    $this->record->forceFill([
                        'status' => 'suspended',
                        'suspended_at' => now(),
                        'suspension_reason' => trim($data['reason']),
                    ])->save();

                    Notification::make()
                        ->success()
                        ->title('Wallet suspended')
                        ->send();

                    $this->redirectToWallet();
                }),

            Action::make('reactivateWallet')
                ->label('Reactivate wallet')
                ->icon('heroicon-o-lock-open')
                ->color('success')
                ->visible(fn (): bool =>
                    (auth()->user()?->can('wallets.manage') ?? false)
                    && $this->record->status === 'suspended')
                ->requiresConfirmation()
                ->action(function (): void {
                    $this->record->forceFill([
                        'status' => 'active',
                        'activated_at' => now(),
                        'suspended_at' => null,
                        'suspension_reason' => null,
                    ])->save();

                    Notification::make()
                        ->success()
                        ->title('Wallet reactivated')
                        ->send();

                    $this->redirectToWallet();
                }),

            Action::make('reverseTransaction')
                ->label('Reverse ledger entry')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->visible(fn (): bool =>
                    (auth()->user()?->can('wallets.manage') ?? false)
                    && $this->reversibleTransactions() !== [])
                ->schema([
                    Select::make('wallet_transaction_id')
                        ->label('Transaction')
                        ->options(fn (): array => $this->reversibleTransactions())
                        ->searchable()
                        ->required(),
                    Textarea::make('reason')
                        ->label('Reversal reason')
                        ->required()
                        ->minLength(10)
                        ->maxLength(2000)
                        ->rows(4),
                ])
                ->requiresConfirmation()
                ->action(function (array $data): void {
                    $transaction = WalletTransaction::query()
                        ->whereKey($data['wallet_transaction_id'])
                        ->where('client_wallet_id', $this->record->id)
                        ->firstOrFail();

                    $actor = auth()->user();
                    abort_unless($actor, 403);

                    app(ReverseWalletTransaction::class)->handle(
                        actor: $actor,
                        transaction: $transaction,
                        reason: $data['reason'],
                    );

                    Notification::make()
                        ->success()
                        ->title('Wallet transaction reversed')
                        ->send();

                    $this->redirectToWallet();
                }),
        ];
    }

    private function reversibleTransactions(): array
    {
        return $this->record->transactions()
            ->whereNotIn('type', [
                WalletTransaction::TYPE_MARKETPLACE_PAYMENT,
                WalletTransaction::TYPE_MARKETPLACE_REFUND,
                WalletTransaction::TYPE_REVERSAL,
            ])
            ->whereDoesntHave('reversals', function ($query): void {
                $query->where('type', WalletTransaction::TYPE_REVERSAL);
            })
            ->latest('occurred_at')
            ->limit(100)
            ->get()
            ->mapWithKeys(fn (WalletTransaction $transaction): array => [
                $transaction->id => sprintf(
                    '%s — %s %s BIF',
                    $transaction->transaction_number,
                    ucfirst($transaction->direction),
                    number_format((float) $transaction->amount, 2),
                ),
            ])
            ->all();
    }

    private function redirectToWallet(): void
    {
        $this->redirect(
            ClientWalletResource::getUrl('view', [
                'record' => $this->record,
            ]),
        );
    }
}
