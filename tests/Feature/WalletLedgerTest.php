<?php

namespace Tests\Feature;

use App\Actions\Wallet\PostWalletTransaction;
use App\Actions\Wallet\RequestWalletFunding;
use App\Actions\Wallet\ReviewWalletFundingRequest;
use App\Actions\Wallet\ReverseWalletTransaction;
use App\Models\ClientProfile;
use App\Models\ClientWallet;
use App\Models\User;
use App\Models\WalletTransaction;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

class WalletLedgerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_approved_funding_request_posts_immutable_credit(): void
    {
        [$client, $wallet] = $this->createClientWallet();

        $finance = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
        $finance->assignRole('finance_manager');

        $request = app(RequestWalletFunding::class)->handle(
            client: $client,
            amount: 50_000,
            fundingMethod: 'demo_credit',
            externalReference: 'DEMO-FUND-001',
        );

        $approved = app(ReviewWalletFundingRequest::class)->approve(
            actor: $finance,
            request: $request,
        );

        $this->assertSame('approved', $approved->status);
        $this->assertSame('50000.00', $wallet->fresh()->available_balance);
        $this->assertDatabaseHas('wallet_transactions', [
            'client_wallet_id' => $wallet->id,
            'direction' => WalletTransaction::DIRECTION_CREDIT,
            'type' => WalletTransaction::TYPE_FUNDING,
            'amount' => 50000,
            'balance_after' => 50000,
        ]);
    }

    public function test_wallet_prevents_insufficient_debit(): void
    {
        [, $wallet] = $this->createClientWallet();

        app(PostWalletTransaction::class)->handle(
            wallet: $wallet,
            direction: WalletTransaction::DIRECTION_CREDIT,
            amount: 5_000,
            type: WalletTransaction::TYPE_MANUAL_ADJUSTMENT,
            description: 'Test opening credit.',
        );

        try {
            app(PostWalletTransaction::class)->handle(
                wallet: $wallet,
                direction: WalletTransaction::DIRECTION_DEBIT,
                amount: 6_000,
                type: WalletTransaction::TYPE_MANUAL_ADJUSTMENT,
                description: 'Invalid debit.',
            );

            $this->fail('An insufficient wallet debit was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('wallet', $exception->errors());
        }

        $this->assertSame('5000.00', $wallet->fresh()->available_balance);
        $this->assertDatabaseCount('wallet_transactions', 1);
    }

    public function test_reversal_posts_opposite_entry_without_editing_original(): void
    {
        [, $wallet] = $this->createClientWallet();

        $finance = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
        $finance->assignRole('finance_manager');

        $credit = app(PostWalletTransaction::class)->handle(
            wallet: $wallet,
            direction: WalletTransaction::DIRECTION_CREDIT,
            amount: 20_000,
            type: WalletTransaction::TYPE_MANUAL_ADJUSTMENT,
            description: 'Manual demonstration credit.',
            actor: $finance,
        );

        $reversal = app(ReverseWalletTransaction::class)->handle(
            actor: $finance,
            transaction: $credit,
            reason: 'Incorrect demonstration credit was recorded.',
        );

        $this->assertSame(WalletTransaction::TYPE_REVERSAL, $reversal->type);
        $this->assertSame(WalletTransaction::DIRECTION_DEBIT, $reversal->direction);
        $this->assertSame($credit->id, $reversal->related_transaction_id);
        $this->assertSame('0.00', $wallet->fresh()->available_balance);
        $this->assertDatabaseCount('wallet_transactions', 2);
    }

    public function test_wallet_transaction_cannot_be_updated_or_deleted(): void
    {
        [, $wallet] = $this->createClientWallet();

        $transaction = app(PostWalletTransaction::class)->handle(
            wallet: $wallet,
            direction: WalletTransaction::DIRECTION_CREDIT,
            amount: 10_000,
            type: WalletTransaction::TYPE_MANUAL_ADJUSTMENT,
            description: 'Immutable entry test.',
        );

        try {
            $transaction->update(['description' => 'Changed']);
            $this->fail('A wallet transaction was modified.');
        } catch (LogicException) {
            $this->assertTrue(true);
        }

        try {
            $transaction->delete();
            $this->fail('A wallet transaction was deleted.');
        } catch (LogicException) {
            $this->assertTrue(true);
        }

        $this->assertDatabaseHas('wallet_transactions', [
            'id' => $transaction->id,
            'description' => 'Immutable entry test.',
        ]);
    }

    private function createClientWallet(): array
    {
        $client = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
        $client->assignRole('client');

        ClientProfile::create([
            'user_id' => $client->id,
            'phone' => '+257 79 500 001',
            'status' => 'active',
        ]);

        $wallet = ClientWallet::create([
            'user_id' => $client->id,
            'currency' => 'BIF',
            'status' => 'active',
        ]);

        return [$client->fresh('wallet'), $wallet];
    }
}
