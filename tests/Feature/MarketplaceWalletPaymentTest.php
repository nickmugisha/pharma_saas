<?php

namespace Tests\Feature;

use App\Actions\Marketplace\PayMarketplaceOrder;
use App\Actions\Marketplace\RefundMarketplaceOrder;
use App\Actions\Marketplace\ReserveMarketplaceOrderStock;
use App\Actions\Wallet\PostWalletTransaction;
use App\Models\ClientProfile;
use App\Models\ClientWallet;
use App\Models\MarketplaceOrder;
use App\Models\MarketplaceOrderItem;
use App\Models\MarketplaceStockReservation;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\Pharmacy;
use App\Models\PharmacyBranch;
use App\Models\PharmacyMedicine;
use App\Models\User;
use App\Models\WalletTransaction;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplaceWalletPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_wallet_payment_confirms_order_and_converts_reservation(): void
    {
        $context = $this->createContext();

        app(PostWalletTransaction::class)->handle(
            wallet: $context['wallet'],
            direction: WalletTransaction::DIRECTION_CREDIT,
            amount: 20_000,
            type: WalletTransaction::TYPE_FUNDING,
            description: 'Approved demonstration funding.',
        );

        $reserved = app(ReserveMarketplaceOrderStock::class)->handle(
            order: $context['order'],
            actor: $context['client'],
        );

        $paid = app(PayMarketplaceOrder::class)->handle(
            client: $context['client'],
            order: $reserved,
        );

        $this->assertSame(MarketplaceOrder::STATUS_CONFIRMED, $paid->status);
        $this->assertSame(MarketplaceOrder::PAYMENT_PAID, $paid->payment_status);
        $this->assertNotNull($paid->paid_at);
        $this->assertSame('13000.00', $context['wallet']->fresh()->available_balance);
        $this->assertSame('8.000', $context['batch']->fresh()->quantity_available);

        $this->assertDatabaseHas('marketplace_stock_reservations', [
            'marketplace_order_id' => $paid->id,
            'status' => MarketplaceStockReservation::STATUS_CONVERTED,
            'quantity' => 2,
        ]);

        $this->assertDatabaseHas('wallet_transactions', [
            'id' => $paid->wallet_payment_transaction_id,
            'type' => WalletTransaction::TYPE_MARKETPLACE_PAYMENT,
            'direction' => WalletTransaction::DIRECTION_DEBIT,
            'amount' => 7000,
        ]);
    }

    public function test_refund_restores_exact_stock_and_wallet_balance(): void
    {
        $context = $this->createContext();

        app(PostWalletTransaction::class)->handle(
            wallet: $context['wallet'],
            direction: WalletTransaction::DIRECTION_CREDIT,
            amount: 20_000,
            type: WalletTransaction::TYPE_FUNDING,
            description: 'Approved demonstration funding.',
        );

        $reserved = app(ReserveMarketplaceOrderStock::class)->handle(
            order: $context['order'],
            actor: $context['client'],
        );

        $paid = app(PayMarketplaceOrder::class)->handle(
            client: $context['client'],
            order: $reserved,
        );

        $refunded = app(RefundMarketplaceOrder::class)->handle(
            actor: $context['owner'],
            order: $paid,
            reason: 'Client order was cancelled before pharmacy fulfilment.',
        );

        $this->assertSame(MarketplaceOrder::STATUS_CANCELLED, $refunded->status);
        $this->assertSame(MarketplaceOrder::PAYMENT_REFUNDED, $refunded->payment_status);
        $this->assertNotNull($refunded->refunded_at);
        $this->assertSame('20000.00', $context['wallet']->fresh()->available_balance);
        $this->assertSame('10.000', $context['batch']->fresh()->quantity_available);

        $this->assertDatabaseHas('marketplace_stock_reservations', [
            'marketplace_order_id' => $refunded->id,
            'status' => MarketplaceStockReservation::STATUS_RELEASED,
            'quantity' => 2,
        ]);

        $this->assertDatabaseHas('wallet_transactions', [
            'id' => $refunded->wallet_refund_transaction_id,
            'type' => WalletTransaction::TYPE_MARKETPLACE_REFUND,
            'direction' => WalletTransaction::DIRECTION_CREDIT,
            'amount' => 7000,
        ]);
    }

    private function createContext(): array
    {
        $pharmacy = Pharmacy::create([
            'name' => 'Wallet Test Pharmacy',
            'phone' => '+257 79 600 001',
            'status' => 'approved',
        ]);

        $branch = PharmacyBranch::create([
            'pharmacy_id' => $pharmacy->id,
            'name' => 'Wallet Test Main Branch',
            'code' => 'WALLET-TEST-MAIN',
            'is_main' => true,
            'status' => 'active',
        ]);

        $owner = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
        $owner->forceFill([
            'pharmacy_id' => $pharmacy->id,
            'pharmacy_branch_id' => $branch->id,
        ])->save();
        $owner->assignRole('pharmacy_owner');

        $client = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
        $client->assignRole('client');

        ClientProfile::create([
            'user_id' => $client->id,
            'phone' => '+257 79 600 002',
            'status' => 'active',
        ]);

        $wallet = ClientWallet::create([
            'user_id' => $client->id,
            'currency' => 'BIF',
            'status' => 'active',
        ]);

        $medicine = Medicine::create([
            'brand_name' => 'Wallet Test Medicine',
            'generic_name' => 'Paracetamol',
            'strength' => '500 mg',
            'approval_status' => 'approved',
            'is_active' => true,
        ]);

        $listing = PharmacyMedicine::create([
            'pharmacy_id' => $pharmacy->id,
            'medicine_id' => $medicine->id,
            'created_by_user_id' => $owner->id,
            'internal_sku' => 'WALLET-MED-001',
            'selling_price' => 3500,
            'online_price' => 3500,
            'is_available' => true,
            'is_visible_online' => true,
            'status' => 'active',
            'minimum_stock_level' => 0,
            'reorder_quantity' => 0,
            'expiry_warning_days' => 90,
            'alerts_enabled' => true,
        ]);

        $batch = MedicineBatch::create([
            'pharmacy_id' => $pharmacy->id,
            'pharmacy_branch_id' => $branch->id,
            'pharmacy_medicine_id' => $listing->id,
            'batch_number' => 'WALLET-BATCH-001',
            'expiry_date' => today()->addYear(),
            'unit_cost' => 2500,
            'quantity_received' => 10,
            'quantity_available' => 10,
            'status' => 'active',
            'received_at' => now(),
        ]);

        $order = MarketplaceOrder::create([
            'user_id' => $client->id,
            'client_wallet_id' => $wallet->id,
            'pharmacy_id' => $pharmacy->id,
            'pharmacy_branch_id' => $branch->id,
            'status' => MarketplaceOrder::STATUS_DRAFT,
            'payment_status' => MarketplaceOrder::PAYMENT_UNPAID,
            'prescription_status' => 'not_required',
            'fulfillment_method' => 'pickup',
            'client_name' => $client->name,
            'client_email' => $client->email,
            'subtotal' => 7000,
            'delivery_fee' => 0,
            'grand_total' => 7000,
            'currency' => 'BIF',
            'placed_at' => now(),
        ]);

        MarketplaceOrderItem::create([
            'marketplace_order_id' => $order->id,
            'medicine_id' => $medicine->id,
            'pharmacy_medicine_id' => $listing->id,
            'medicine_name' => $medicine->brand_name,
            'strength' => $medicine->strength,
            'sku' => $listing->internal_sku,
            'quantity' => 2,
            'unit_price' => 3500,
            'line_total' => 7000,
            'online_sale_mode' => 'otc',
            'prescription_review_status' => 'not_required',
        ]);

        return compact(
            'pharmacy',
            'branch',
            'owner',
            'client',
            'wallet',
            'medicine',
            'listing',
            'batch',
            'order',
        );
    }
}
