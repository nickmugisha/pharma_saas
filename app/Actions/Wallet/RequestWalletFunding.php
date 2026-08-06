<?php

namespace App\Actions\Wallet;

use App\Models\User;
use App\Models\WalletFundingRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RequestWalletFunding
{
    private const METHODS = [
        'cash_deposit',
        'mobile_money',
        'bank_transfer',
        'demo_credit',
    ];

    public function handle(
        User $client,
        float $amount,
        string $fundingMethod,
        ?string $externalReference = null,
        ?string $notes = null,
    ): WalletFundingRequest {
        abort_unless(
            $client->is_active && $client->hasRole('client'),
            403,
        );

        $wallet = $client->wallet;

        if (! $wallet || $wallet->status !== 'active') {
            throw ValidationException::withMessages([
                'wallet' => 'An active wallet is required.',
            ]);
        }

        $amount = round($amount, 2);

        if ($amount < 1000 || $amount > 5_000_000) {
            throw ValidationException::withMessages([
                'amount' => 'Funding requests must be between 1,000 and 5,000,000 BIF.',
            ]);
        }

        if (! in_array($fundingMethod, self::METHODS, true)) {
            throw ValidationException::withMessages([
                'funding_method' => 'The selected funding method is invalid.',
            ]);
        }

        return DB::transaction(function () use (
            $client,
            $wallet,
            $amount,
            $fundingMethod,
            $externalReference,
            $notes,
        ): WalletFundingRequest {
            $pendingCount = WalletFundingRequest::query()
                ->where('client_wallet_id', $wallet->id)
                ->where('status', WalletFundingRequest::STATUS_PENDING)
                ->lockForUpdate()
                ->count();

            if ($pendingCount >= 3) {
                throw ValidationException::withMessages([
                    'wallet' => 'You already have three pending funding requests.',
                ]);
            }

            return WalletFundingRequest::create([
                'client_wallet_id' => $wallet->id,
                'user_id' => $client->id,
                'amount' => $amount,
                'currency' => $wallet->currency,
                'funding_method' => $fundingMethod,
                'external_reference' => filled($externalReference)
                    ? trim($externalReference)
                    : null,
                'status' => WalletFundingRequest::STATUS_PENDING,
                'requested_at' => now(),
                'notes' => filled($notes) ? trim($notes) : null,
            ]);
        }, attempts: 5);
    }
}
