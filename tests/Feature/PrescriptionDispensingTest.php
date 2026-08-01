<?php

namespace Tests\Feature;

use App\Actions\Prescriptions\DispensePrescription;
use App\Models\Customer;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\Pharmacy;
use App\Models\PharmacyBranch;
use App\Models\PharmacyMedicine;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;
use App\Filament\Pharmacy\Resources\Prescriptions\Pages\ViewPrescription;
use Filament\Facades\Filament;
use Livewire\Livewire;
use App\Actions\Sales\VoidCompletedSale;

class PrescriptionDispensingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Filament::setCurrentPanel('pharmacy');
    }

    public function test_prescription_can_be_partially_dispensed(): void
    {
        $context = $this->createContext('PARTIAL');

        $batch = $this->createBatch(
            context: $context,
            suffix: 'PARTIAL',
            quantity: 10,
            expiryDays: 30,
        );

        $prescription = $this->createApprovedPrescription(
            context: $context,
            quantity: 10,
        );

        $dispensing = $this->dispense(
            context: $context,
            prescription: $prescription,
            quantity: 4,
            paymentAmount: 14000,
        );

        $prescription->refresh();
        $prescription->items->first()->refresh();

        $item = $prescription->items->first();
        $sale = $dispensing->sale;

        $this->assertSame(
            Prescription::STATUS_PARTIALLY_DISPENSED,
            $prescription->status,
        );

        $this->assertNull($prescription->dispensed_at);

        $this->assertSame(
            '4.000',
            $item->quantity_dispensed,
        );

        $this->assertSame(
            'partially_dispensed',
            $item->status,
        );

        $this->assertSame('completed', $sale->status);
        $this->assertSame('paid', $sale->payment_status);

        $this->assertSame(
            $prescription->customer_id,
            $sale->customer_id,
        );

        $this->assertSame(
            $prescription->id,
            (int) $sale->source_id,
        );

        $this->assertSame(
            '6.000',
            $batch->fresh()->quantity_available,
        );

        $this->assertDatabaseHas(
            'prescription_dispensing_items',
            [
                'prescription_dispensing_id' =>
                    $dispensing->id,

                'prescription_item_id' =>
                    $item->id,

                'quantity_dispensed' => 4,
            ],
        );

        $this->assertDatabaseHas(
            'prescription_activities',
            [
                'prescription_id' =>
                    $prescription->id,

                'activity_type' =>
                    'partially_dispensed',
            ],
        );
    }

    public function test_second_dispensing_can_complete_prescription(): void
    {
        $context = $this->createContext('COMPLETE');

        $batch = $this->createBatch(
            context: $context,
            suffix: 'COMPLETE',
            quantity: 10,
            expiryDays: 30,
        );

        $prescription = $this->createApprovedPrescription(
            context: $context,
            quantity: 10,
        );

        $this->dispense(
            context: $context,
            prescription: $prescription,
            quantity: 4,
            paymentAmount: 14000,
        );

        $secondDispensing = $this->dispense(
            context: $context,
            prescription: $prescription->fresh(),
            quantity: 6,
            paymentAmount: 21000,
        );

        $prescription->refresh();
        $item = $prescription
            ->items()
            ->firstOrFail();

        $this->assertSame(
            Prescription::STATUS_DISPENSED,
            $prescription->status,
        );

        $this->assertNotNull(
            $prescription->dispensed_at,
        );

        $this->assertSame(
            '10.000',
            $item->quantity_dispensed,
        );

        $this->assertSame(
            'dispensed',
            $item->status,
        );

        $this->assertSame(
            '0.000',
            $batch->fresh()->quantity_available,
        );

        $this->assertSame(
            'depleted',
            $batch->fresh()->status,
        );

        $this->assertDatabaseCount(
            'prescription_dispensings',
            2,
        );

        $this->assertDatabaseCount('sales', 2);

        $this->assertDatabaseHas(
            'prescription_activities',
            [
                'prescription_id' =>
                    $prescription->id,

                'activity_type' => 'dispensed',
            ],
        );

        $this->assertSame(
            $prescription->id,
            $secondDispensing->prescription_id,
        );
    }

    public function test_dispensing_uses_fefo_and_maps_exact_sale_item(): void
    {
        $context = $this->createContext('FEFO');

        $firstBatch = $this->createBatch(
            context: $context,
            suffix: 'EARLY',
            quantity: 3,
            expiryDays: 10,
        );

        $secondBatch = $this->createBatch(
            context: $context,
            suffix: 'LATER',
            quantity: 10,
            expiryDays: 40,
        );

        $prescription = $this->createApprovedPrescription(
            context: $context,
            quantity: 5,
        );

        $dispensing = $this->dispense(
            context: $context,
            prescription: $prescription,
            quantity: 5,
            paymentAmount: 17500,
        );

        $saleItem = $dispensing
            ->sale
            ->items()
            ->firstOrFail();

        $allocations = $saleItem
            ->batchAllocations()
            ->orderBy('id')
            ->get();

        $dispensingItem = $dispensing
            ->items()
            ->firstOrFail();

        $this->assertCount(2, $allocations);

        $this->assertSame(
            $firstBatch->id,
            $allocations[0]->medicine_batch_id,
        );

        $this->assertSame(
            '3.000',
            $allocations[0]->quantity,
        );

        $this->assertSame(
            $secondBatch->id,
            $allocations[1]->medicine_batch_id,
        );

        $this->assertSame(
            '2.000',
            $allocations[1]->quantity,
        );

        $this->assertSame(
            $saleItem->id,
            $dispensingItem->sale_item_id,
        );

        $this->assertSame(
            $prescription
                ->items()
                ->firstOrFail()
                ->id,

            $dispensingItem
                ->prescription_item_id,
        );

        $this->assertSame(
            '0.000',
            $firstBatch->fresh()->quantity_available,
        );

        $this->assertSame(
            '8.000',
            $secondBatch->fresh()->quantity_available,
        );

        $this->assertDatabaseCount(
            'stock_movements',
            2,
        );
    }

    public function test_over_dispensing_is_rejected_without_changing_stock(): void
    {
        $context = $this->createContext('OVER');

        $batch = $this->createBatch(
            context: $context,
            suffix: 'OVER',
            quantity: 10,
            expiryDays: 30,
        );

        $prescription = $this->createApprovedPrescription(
            context: $context,
            quantity: 5,
        );

        try {
            $this->dispense(
                context: $context,
                prescription: $prescription,
                quantity: 6,
                paymentAmount: 21000,
            );

            $this->fail(
                'The prescription was over-dispensed.',
            );
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'lines.0.quantity',
                $exception->errors(),
            );
        }

        $item = $prescription
            ->items()
            ->firstOrFail()
            ->fresh();

        $this->assertSame(
            Prescription::STATUS_APPROVED,
            $prescription->fresh()->status,
        );

        $this->assertSame(
            '0.000',
            $item->quantity_dispensed,
        );

        $this->assertSame(
            '10.000',
            $batch->fresh()->quantity_available,
        );

        $this->assertDatabaseCount('sales', 0);

        $this->assertDatabaseCount(
            'prescription_dispensings',
            0,
        );

        $this->assertDatabaseCount(
            'prescription_dispensing_items',
            0,
        );

        $this->assertDatabaseCount(
            'stock_movements',
            0,
        );
    }

    public function test_payment_failure_rolls_back_dispensing_and_stock(): void
    {
        $context = $this->createContext('PAYMENT');

        $batch = $this->createBatch(
            context: $context,
            suffix: 'PAYMENT',
            quantity: 10,
            expiryDays: 30,
        );

        $prescription = $this->createApprovedPrescription(
            context: $context,
            quantity: 5,
        );

        try {
            app(DispensePrescription::class)->handle(
                actor: $context['pharmacist'],
                prescription: $prescription,
                lines: [[
                    'prescription_item_id' =>
                        $prescription
                            ->items()
                            ->firstOrFail()
                            ->id,

                    'pharmacy_medicine_id' =>
                        $context['listing']->id,

                    'quantity' => 2,
                    'discount_amount' => 0,
                    'tax_rate' => 0,
                ]],
                payments: [[
                    'payment_method' =>
                        'mobile_money',

                    'amount' => 8000,

                    'reference' =>
                        'MM-RX-OVERPAYMENT',
                ]],
            );

            $this->fail(
                'An invalid payment completed a dispensing.',
            );
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'payments',
                $exception->errors(),
            );
        }

        $this->assertSame(
            '10.000',
            $batch->fresh()->quantity_available,
        );

        $this->assertSame(
            Prescription::STATUS_APPROVED,
            $prescription->fresh()->status,
        );

        $this->assertSame(
            '0.000',
            $prescription
                ->items()
                ->firstOrFail()
                ->fresh()
                ->quantity_dispensed,
        );

        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('sale_items', 0);
        $this->assertDatabaseCount('sale_payments', 0);

        $this->assertDatabaseCount(
            'prescription_dispensings',
            0,
        );

        $this->assertDatabaseCount(
            'stock_movements',
            0,
        );
    }

    public function test_dispensing_enforces_permission_tenant_and_branch(): void
    {
        $contextA = $this->createContext('ACCESS-A');
        $contextB = $this->createContext('ACCESS-B');

        $prescriptionB =
            $this->createApprovedPrescription(
                context: $contextB,
                quantity: 5,
            );

        try {
            $this->dispense(
                context: $contextA,
                prescription: $prescriptionB,
                quantity: 1,
                paymentAmount: 3500,
            );

            $this->fail(
                'A foreign prescription was dispensed.',
            );
        } catch (HttpException $exception) {
            $this->assertSame(
                403,
                $exception->getStatusCode(),
            );
        }

        $secondBranch = PharmacyBranch::create([
            'pharmacy_id' =>
                $contextB['pharmacy']->id,

            'name' => 'Different Dispensing Branch',

            'code' => 'RX-DIFFERENT-BRANCH',

            'is_main' => false,
            'status' => 'active',
        ]);

        $otherBranchPharmacist =
            $this->createPharmacist(
                pharmacy: $contextB['pharmacy'],
                branch: $secondBranch,
            );

        try {
            app(DispensePrescription::class)->handle(
                actor: $otherBranchPharmacist,
                prescription: $prescriptionB,
                lines: [[
                    'prescription_item_id' =>
                        $prescriptionB
                            ->items()
                            ->firstOrFail()
                            ->id,

                    'pharmacy_medicine_id' =>
                        $contextB['listing']->id,

                    'quantity' => 1,
                ]],
                payments: [[
                    'payment_method' => 'cash',
                    'amount' => 3500,
                ]],
            );

            $this->fail(
                'A different branch dispensed the prescription.',
            );
        } catch (HttpException $exception) {
            $this->assertSame(
                403,
                $exception->getStatusCode(),
            );
        }

        $viewer = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $viewer->forceFill([
            'pharmacy_id' =>
                $contextB['pharmacy']->id,

            'pharmacy_branch_id' =>
                $contextB['branch']->id,
        ])->save();

        $viewer->givePermissionTo([
            'prescriptions.view',
            'sales.create',
        ]);

        try {
            app(DispensePrescription::class)->handle(
                actor: $viewer,
                prescription: $prescriptionB,
                lines: [[
                    'prescription_item_id' =>
                        $prescriptionB
                            ->items()
                            ->firstOrFail()
                            ->id,

                    'pharmacy_medicine_id' =>
                        $contextB['listing']->id,

                    'quantity' => 1,
                ]],
                payments: [[
                    'payment_method' => 'cash',
                    'amount' => 3500,
                ]],
            );

            $this->fail(
                'A user without dispensing permission dispensed medicine.',
            );
        } catch (HttpException $exception) {
            $this->assertSame(
                403,
                $exception->getStatusCode(),
            );
        }

        $this->assertDatabaseCount('sales', 0);

        $this->assertDatabaseCount(
            'prescription_dispensings',
            0,
        );
    }

    public function test_approved_prescription_displays_readable_dispensing_modal(): void
{
    $context = $this->createContext('MODAL');

    $this->createBatch(
        context: $context,
        suffix: 'MODAL',
        quantity: 10,
        expiryDays: 30,
    );

    $prescription = $this->createApprovedPrescription(
        context: $context,
        quantity: 5,
    );

    $this->actingAs($context['pharmacist']);

    Livewire::test(
        ViewPrescription::class,
        [
            'record' =>
                $prescription->getRouteKey(),
        ],
    )
        ->assertOk()
        ->assertActionVisible('dispense')
        ->mountAction('dispense')
        ->assertMountedActionModalSee(
            'Dispense prescription medicines',
        )
        ->assertMountedActionModalSee(
            'MODAL Dispensing Medicine',
        )
        ->assertMountedActionModalSee(
            '5.000 remaining',
        )
        ->assertMountedActionModalSee(
            'Available non-expired stock',
        )
        ->assertMountedActionModalSee('Cash');
}

public function test_dispensing_action_is_hidden_for_invalid_statuses_and_expired_prescription(): void
{
    $context = $this->createContext('STATUS');

    $this->createBatch(
        context: $context,
        suffix: 'STATUS',
        quantity: 50,
        expiryDays: 60,
    );

    $this->actingAs($context['pharmacist']);

    $invalidStatuses = [
        Prescription::STATUS_DRAFT,
        Prescription::STATUS_SUBMITTED,
        Prescription::STATUS_UNDER_REVIEW,
        Prescription::STATUS_REJECTED,
        Prescription::STATUS_DISPENSED,
        Prescription::STATUS_CANCELLED,
    ];

    foreach ($invalidStatuses as $status) {
        $prescription =
            $this->createApprovedPrescription(
                context: $context,
                quantity: 5,
            );

        $prescription->forceFill([
            'status' => $status,
        ])->save();

        Livewire::test(
            ViewPrescription::class,
            [
                'record' =>
                    $prescription->getRouteKey(),
            ],
        )
            ->assertOk()
            ->assertActionHidden('dispense');
    }

    $expiredPrescription =
        $this->createApprovedPrescription(
            context: $context,
            quantity: 5,
        );

    $expiredPrescription->forceFill([
        'valid_until' => today()->subDay(),
    ])->save();

    Livewire::test(
        ViewPrescription::class,
        [
            'record' =>
                $expiredPrescription
                    ->getRouteKey(),
        ],
    )
        ->assertOk()
        ->assertActionHidden('dispense');
}

public function test_dispensing_action_is_hidden_without_permission_or_for_another_branch(): void
{
    $context = $this->createContext('SECURITY');

    $this->createBatch(
        context: $context,
        suffix: 'SECURITY',
        quantity: 10,
        expiryDays: 30,
    );

    $prescription =
        $this->createApprovedPrescription(
            context: $context,
            quantity: 5,
        );

    $assistant = User::factory()->create([
        'email_verified_at' => now(),
        'is_active' => true,
    ]);

    $assistant->forceFill([
        'pharmacy_id' =>
            $context['pharmacy']->id,

        'pharmacy_branch_id' =>
            $context['branch']->id,
    ])->save();

    $assistant->assignRole(
        'pharmacy_assistant',
    );

    $this->actingAs($assistant);

    Livewire::test(
        ViewPrescription::class,
        [
            'record' =>
                $prescription->getRouteKey(),
        ],
    )
        ->assertOk()
        ->assertActionHidden('dispense');

    $secondBranch = PharmacyBranch::create([
        'pharmacy_id' =>
            $context['pharmacy']->id,

        'name' =>
            'Security Secondary Branch',

        'code' =>
            'RX-SECURITY-SECONDARY',

        'is_main' => false,
        'status' => 'active',
    ]);

    $otherBranchPharmacist =
        $this->createPharmacist(
            pharmacy: $context['pharmacy'],
            branch: $secondBranch,
        );

    $this->actingAs(
        $otherBranchPharmacist,
    );

    Livewire::test(
        ViewPrescription::class,
        [
            'record' =>
                $prescription->getRouteKey(),
        ],
    )
        ->assertOk()
        ->assertActionHidden('dispense');
}

public function test_completed_dispensing_is_displayed_in_immutable_history(): void
{
    $context = $this->createContext('HISTORY');

    $this->createBatch(
        context: $context,
        suffix: 'HISTORY',
        quantity: 10,
        expiryDays: 30,
    );

    $prescription =
        $this->createApprovedPrescription(
            context: $context,
            quantity: 5,
        );

    $dispensing = $this->dispense(
        context: $context,
        prescription: $prescription,
        quantity: 5,
        paymentAmount: 17500,
    );

    $sale = $dispensing->sale;

    $this->actingAs($context['pharmacist'])
        ->get(
            "/pharmacy/prescriptions/{$prescription->id}",
        )
        ->assertOk()
        ->assertSee('Dispensing history')
        ->assertSee(
            $dispensing->dispensing_number,
        )
        ->assertSee($sale->sale_number)
        ->assertSee($sale->receipt_number)
        ->assertSee(
            'HISTORY Dispensing Medicine',
        )
        ->assertSee('5.000 unit(s)');
}

public function test_voiding_only_dispensing_restores_prescription_to_approved(): void
{
    $context = $this->createContext('VOID-ONLY');

    $batch = $this->createBatch(
        context: $context,
        suffix: 'VOID-ONLY',
        quantity: 5,
        expiryDays: 30,
    );

    $prescription =
        $this->createApprovedPrescription(
            context: $context,
            quantity: 5,
        );

    $dispensing = $this->dispense(
        context: $context,
        prescription: $prescription,
        quantity: 5,
        paymentAmount: 17500,
    );

    $this->assertSame(
        Prescription::STATUS_DISPENSED,
        $prescription->fresh()->status,
    );

    $context['pharmacist']
        ->givePermissionTo('sales.void');

    $voidedSale = app(
        VoidCompletedSale::class,
    )->handle(
        user: $context['pharmacist'],
        sale: $dispensing->sale,
        reason:
            'Prescription medicine returned by customer.',
    );

    $prescription->refresh();

    $prescriptionItem = $prescription
        ->items()
        ->firstOrFail();

    $dispensing->refresh();

    $this->assertSame(
        'voided',
        $voidedSale->status,
    );

    $this->assertSame(
        'refunded',
        $voidedSale->payment_status,
    );

    $this->assertSame(
        'voided',
        $dispensing->status,
    );

    $this->assertSame(
        $context['pharmacist']->id,
        $dispensing->voided_by_user_id,
    );

    $this->assertNotNull(
        $dispensing->voided_at,
    );

    $this->assertSame(
        Prescription::STATUS_APPROVED,
        $prescription->status,
    );

    $this->assertNull(
        $prescription->dispensed_at,
    );

    $this->assertSame(
        '0.000',
        $prescriptionItem->quantity_dispensed,
    );

    $this->assertSame(
        'pending',
        $prescriptionItem->status,
    );

    $this->assertSame(
        '5.000',
        $batch->fresh()->quantity_available,
    );

    $this->assertDatabaseHas(
        'prescription_activities',
        [
            'prescription_id' =>
                $prescription->id,

            'activity_type' =>
                'dispensing_voided',
        ],
    );

    $this->assertDatabaseHas(
        'stock_movements',
        [
            'medicine_batch_id' =>
                $batch->id,

            'movement_type' =>
                'sale_void',

            'direction' => 'in',

            'quantity' => 5,
        ],
    );
}

public function test_voiding_second_dispensing_preserves_first_partial_dispensing(): void
{
    $context = $this->createContext('VOID-PARTIAL');

    $batch = $this->createBatch(
        context: $context,
        suffix: 'VOID-PARTIAL',
        quantity: 10,
        expiryDays: 30,
    );

    $prescription =
        $this->createApprovedPrescription(
            context: $context,
            quantity: 10,
        );

    $firstDispensing = $this->dispense(
        context: $context,
        prescription: $prescription,
        quantity: 4,
        paymentAmount: 14000,
    );

    $secondDispensing = $this->dispense(
        context: $context,
        prescription: $prescription->fresh(),
        quantity: 6,
        paymentAmount: 21000,
    );

    $this->assertSame(
        Prescription::STATUS_DISPENSED,
        $prescription->fresh()->status,
    );

    $context['pharmacist']
        ->givePermissionTo('sales.void');

    app(VoidCompletedSale::class)->handle(
        user: $context['pharmacist'],
        sale: $secondDispensing->sale,
        reason:
            'Second prescription dispensing was entered incorrectly.',
    );

    $prescription->refresh();

    $prescriptionItem = $prescription
        ->items()
        ->firstOrFail();

    $firstDispensing->refresh();
    $secondDispensing->refresh();

    $this->assertSame(
        'completed',
        $firstDispensing->status,
    );

    $this->assertSame(
        'voided',
        $secondDispensing->status,
    );

    $this->assertSame(
        Prescription::STATUS_PARTIALLY_DISPENSED,
        $prescription->status,
    );

    $this->assertNull(
        $prescription->dispensed_at,
    );

    $this->assertSame(
        '4.000',
        $prescriptionItem->quantity_dispensed,
    );

    $this->assertSame(
        'partially_dispensed',
        $prescriptionItem->status,
    );

    $this->assertSame(
        '6.000',
        $batch->fresh()->quantity_available,
    );

    $this->assertDatabaseHas(
        'prescription_dispensings',
        [
            'id' => $firstDispensing->id,
            'status' => 'completed',
        ],
    );

    $this->assertDatabaseHas(
        'prescription_dispensings',
        [
            'id' => $secondDispensing->id,
            'status' => 'voided',
        ],
    );

    $this->assertDatabaseHas(
        'prescription_activities',
        [
            'prescription_id' =>
                $prescription->id,

            'activity_type' =>
                'dispensing_voided',
        ],
    );
}

    private function createContext(
        string $suffix,
    ): array {
        $pharmacy = Pharmacy::create([
            'name' =>
                "{$suffix} Dispensing Pharmacy",

            'phone' => '+257 61 '.random_int(
                100000,
                999999,
            ),

            'status' => 'approved',
        ]);

        $branch = PharmacyBranch::create([
            'pharmacy_id' => $pharmacy->id,

            'name' =>
                "{$suffix} Dispensing Branch",

            'code' => 'RXD-'.strtoupper(
                substr(md5($suffix), 0, 8),
            ),

            'is_main' => true,
            'status' => 'active',
        ]);

        $pharmacist = $this->createPharmacist(
            pharmacy: $pharmacy,
            branch: $branch,
        );

        $customer = Customer::create([
            'pharmacy_id' => $pharmacy->id,

            'registered_branch_id' =>
                $branch->id,

            'name' =>
                "{$suffix} Dispensing Customer",

            'phone' => '+257 79 '.random_int(
                100000,
                999999,
            ),

            'status' => 'active',
        ]);

        $medicine = Medicine::create([
            'brand_name' =>
                "{$suffix} Dispensing Medicine",

            'approval_status' => 'approved',
            'is_active' => true,
        ]);

        $listing = PharmacyMedicine::create([
            'pharmacy_id' => $pharmacy->id,
            'medicine_id' => $medicine->id,

            'sku' => 'RXD-SKU-'.strtoupper(
                substr(md5($suffix), 0, 10),
            ),

            'selling_price' => 3500,
            'minimum_stock_level' => 0,
            'reorder_quantity' => 0,
            'expiry_warning_days' => 90,
            'alerts_enabled' => true,
            'status' => 'active',
        ]);

        return compact(
            'pharmacy',
            'branch',
            'pharmacist',
            'customer',
            'medicine',
            'listing',
        );
    }

    private function createPharmacist(
        Pharmacy $pharmacy,
        PharmacyBranch $branch,
    ): User {
        $pharmacist = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $pharmacist->forceFill([
            'pharmacy_id' => $pharmacy->id,

            'pharmacy_branch_id' =>
                $branch->id,
        ])->save();

        $pharmacist->assignRole('pharmacist');

        return $pharmacist;
    }

    private function createApprovedPrescription(
        array $context,
        float $quantity,
    ): Prescription {
        $prescription = Prescription::create([
            'pharmacy_id' =>
                $context['pharmacy']->id,

            'pharmacy_branch_id' =>
                $context['branch']->id,

            'customer_id' =>
                $context['customer']->id,

            'created_by_user_id' =>
                $context['pharmacist']->id,

            'reviewed_by_user_id' =>
                $context['pharmacist']->id,

            'status' =>
                Prescription::STATUS_APPROVED,

            'source' => 'manual',

            'prescriber_name' =>
                'Dr Dispensing Test',

            'prescriber_facility' =>
                'Dispensing Test Clinic',

            'issued_at' => today(),

            'valid_until' =>
                today()->addDays(30),

            'reviewed_at' => now(),
            'approved_at' => now(),
        ]);

        PrescriptionItem::create([
            'prescription_id' =>
                $prescription->id,

            'medicine_id' =>
                $context['medicine']->id,

            'pharmacy_medicine_id' =>
                $context['listing']->id,

            'prescribed_name' =>
                $context['medicine']->brand_name,

            'strength' => '500 mg',
            'dosage_form' => 'Tablet',
            'dosage' => '1 tablet',
            'frequency' => 'Twice daily',
            'duration' => '5 days',

            'quantity_prescribed' =>
                $quantity,

            'quantity_dispensed' => 0,
            'substitution_allowed' => false,
            'status' => 'pending',
        ]);

        return $prescription->fresh([
            'customer',
            'branch',
            'items',
        ]);
    }

    private function createBatch(
        array $context,
        string $suffix,
        float $quantity,
        int $expiryDays,
    ): MedicineBatch {
        return MedicineBatch::create([
            'pharmacy_id' =>
                $context['pharmacy']->id,

            'pharmacy_branch_id' =>
                $context['branch']->id,

            'pharmacy_medicine_id' =>
                $context['listing']->id,

            'batch_number' => "RXD-LOT-{$suffix}",

            'expiry_date' =>
                today()->addDays($expiryDays),

            'unit_cost' => 2500,

            'quantity_received' =>
                $quantity,

            'quantity_available' =>
                $quantity,

            'status' => 'active',
            'received_at' => now(),
        ]);
    }

    private function dispense(
        array $context,
        Prescription $prescription,
        float $quantity,
        float $paymentAmount,
    ) {
        return app(DispensePrescription::class)
            ->handle(
                actor: $context['pharmacist'],

                prescription: $prescription,

                lines: [[
                    'prescription_item_id' =>
                        $prescription
                            ->items()
                            ->firstOrFail()
                            ->id,

                    'pharmacy_medicine_id' =>
                        $context['listing']->id,

                    'quantity' => $quantity,
                    'discount_amount' => 0,
                    'tax_rate' => 0,
                ]],

                payments: [[
                    'payment_method' => 'cash',
                    'amount' => $paymentAmount,
                ]],
            );
    }
}