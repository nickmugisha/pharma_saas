<?php

namespace App\Filament\Resources\WalletFundingRequests\Pages;

use App\Actions\Wallet\ReviewWalletFundingRequest;
use App\Filament\Resources\WalletFundingRequests\WalletFundingRequestResource;
use App\Models\User;
use App\Models\WalletFundingRequest;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewWalletFundingRequest extends ViewRecord
{
    protected static string $resource = WalletFundingRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approveFunding')
                ->label('Approve funding')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription(
                    'Approval posts an immutable credit to the client wallet.',
                )
                ->visible(fn (): bool =>
                    $this->record->status === WalletFundingRequest::STATUS_PENDING
                    && (auth()->user()?->can('wallets.funding.review') ?? false))
                ->action(function (): void {
                    app(ReviewWalletFundingRequest::class)->approve(
                        actor: $this->currentUser(),
                        request: $this->record,
                    );

                    Notification::make()
                        ->success()
                        ->title('Wallet funding approved')
                        ->send();

                    $this->redirectToRecord();
                }),

            Action::make('rejectFunding')
                ->label('Reject funding')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (): bool =>
                    $this->record->status === WalletFundingRequest::STATUS_PENDING
                    && (auth()->user()?->can('wallets.funding.review') ?? false))
                ->schema([
                    Textarea::make('reason')
                        ->label('Rejection reason')
                        ->required()
                        ->minLength(5)
                        ->maxLength(2000)
                        ->rows(4),
                ])
                ->action(function (array $data): void {
                    app(ReviewWalletFundingRequest::class)->reject(
                        actor: $this->currentUser(),
                        request: $this->record,
                        reason: $data['reason'],
                    );

                    Notification::make()
                        ->success()
                        ->title('Wallet funding rejected')
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
        $this->redirect(
            WalletFundingRequestResource::getUrl('view', [
                'record' => $this->record,
            ]),
        );
    }
}
