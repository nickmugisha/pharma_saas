<?php

namespace App\Actions\Wallet;

use App\Models\User;
use App\Models\WalletFundingRequest;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviewWalletFundingRequest
{
    public function approve(
        User $actor,
        WalletFundingRequest $request,
    ): WalletFundingRequest {
        $this->authorize($actor);

        return DB::transaction(function () use (
            $actor,
            $request,
        ): WalletFundingRequest {
            $lockedRequest = WalletFundingRequest::query()
                ->with('wallet')
                ->whereKey($request->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensurePending($lockedRequest);

            $transaction = app(PostWalletTransaction::class)->handle(
                wallet: $lockedRequest->wallet,
                direction: WalletTransaction::DIRECTION_CREDIT,
                amount: (float) $lockedRequest->amount,
                type: WalletTransaction::TYPE_FUNDING,
                description: sprintf(
                    'Approved wallet funding request %s.',
                    $lockedRequest->request_number,
                ),
                actor: $actor,
                source: $lockedRequest,
                metadata: [
                    'funding_method' => $lockedRequest->funding_method,
                    'external_reference' => $lockedRequest->external_reference,
                ],
                idempotencyKey: "wallet_funding_request:{$lockedRequest->id}",
            );

            $lockedRequest->forceFill([
                'status' => WalletFundingRequest::STATUS_APPROVED,
                'reviewed_by_user_id' => $actor->id,
                'wallet_transaction_id' => $transaction->id,
                'reviewed_at' => now(),
                'rejection_reason' => null,
            ])->save();

            return $lockedRequest->fresh([
                'wallet.user',
                'reviewedByUser',
                'walletTransaction',
            ]);
        }, attempts: 5);
    }

    public function reject(
        User $actor,
        WalletFundingRequest $request,
        string $reason,
    ): WalletFundingRequest {
        $this->authorize($actor);
        $reason = trim($reason);

        if (mb_strlen($reason) < 5) {
            throw ValidationException::withMessages([
                'reason' => 'The rejection reason must contain at least five characters.',
            ]);
        }

        return DB::transaction(function () use (
            $actor,
            $request,
            $reason,
        ): WalletFundingRequest {
            $lockedRequest = WalletFundingRequest::query()
                ->whereKey($request->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensurePending($lockedRequest);

            $lockedRequest->forceFill([
                'status' => WalletFundingRequest::STATUS_REJECTED,
                'reviewed_by_user_id' => $actor->id,
                'reviewed_at' => now(),
                'rejection_reason' => $reason,
            ])->save();

            return $lockedRequest->fresh([
                'wallet.user',
                'reviewedByUser',
            ]);
        }, attempts: 5);
    }

    private function authorize(User $actor): void
    {
        abort_unless(
            $actor->can('wallets.funding.review'),
            403,
        );
    }

    private function ensurePending(
        WalletFundingRequest $request,
    ): void {
        if ($request->status !== WalletFundingRequest::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'request' => 'This funding request has already been reviewed.',
            ]);
        }
    }
}
