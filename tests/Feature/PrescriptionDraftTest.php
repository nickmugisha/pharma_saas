<?php

namespace Tests\Feature;

use App\Actions\Prescriptions\SavePrescriptionDraft;
use App\Models\Customer;
use App\Models\Medicine;
use App\Models\Pharmacy;
use App\Models\PharmacyBranch;
use App\Models\PharmacyMedicine;
use App\Models\Prescription;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PrescriptionDraftTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_assistant_can_create_prescription_draft_with_selected_records(): void
    {
        $context = $this->createContext('CREATE');

        $prescription = app(SavePrescriptionDraft::class)
            ->create(
                actor: $context['assistant'],
                data: $this->validData($context),
            );

        $this->assertSame(
            Prescription::STATUS_DRAFT,
            $prescription->status,
        );

        $this->assertSame(
            $context['customer']->id,
            $prescription->customer_id,
        );

        $this->assertSame(
            $context['branch']->id,
            $prescription->pharmacy_branch_id,
        );

        $this->assertCount(1, $prescription->items);

        $this->assertSame(
            $context['listing']->id,
            $prescription->items
                ->first()
                ->pharmacy_medicine_id,
        );

        $this->assertDatabaseHas(
            'prescription_activities',
            [
                'prescription_id' => $prescription->id,
                'activity_type' => 'created',
            ],
        );

        $this->assertDatabaseHas(
            'customer_activities',
            [
                'customer_id' => $context['customer']->id,
                'activity_type' => 'prescription_created',
            ],
        );
    }

    public function test_draft_update_replaces_items_and_preserves_attachments(): void
    {
        Storage::fake('local');

        $context = $this->createContext('UPDATE');

        $path = sprintf(
            'prescriptions/%d/original-prescription.pdf',
            $context['pharmacy']->id,
        );

        Storage::disk('local')->put(
            $path,
            'initial prescription document',
        );

        $createData = $this->validData(
            $context,
            'uploaded',
        );

        $createData['new_attachment_paths'] = [
            $path,
        ];

        $createData['attachment_original_names'] = [
            $path => 'Original Prescription.pdf',
        ];

        $prescription = app(SavePrescriptionDraft::class)
            ->create(
                actor: $context['assistant'],
                data: $createData,
            );

        $replacementListing = $this->createListing(
            $context['pharmacy'],
            'Replacement Medicine',
            'UPDATE-REPLACEMENT',
        );

        $updateData = $this->validData(
            $context,
            'uploaded',
        );

        $updateData['prescriber_name'] =
            'Dr Updated Prescriber';

        $updateData['items'] = [
            [
                'pharmacy_medicine_id' =>
                    $replacementListing->id,

                'prescribed_name' =>
                    $replacementListing
                        ->medicine
                        ->brand_name,

                'strength' => '250 mg',
                'dosage_form' => 'Tablet',
                'dosage' => '1 tablet',
                'frequency' => 'Twice daily',
                'duration' => '5 days',
                'quantity_prescribed' => 10,
                'substitution_allowed' => true,
                'instructions' => 'Take with water.',
            ],
        ];

        $updated = app(SavePrescriptionDraft::class)
            ->update(
                actor: $context['assistant'],
                prescription: $prescription,
                data: $updateData,
            );

        $this->assertSame(
            'Dr Updated Prescriber',
            $updated->prescriber_name,
        );

        $this->assertCount(1, $updated->items);

        $this->assertSame(
            $replacementListing->id,
            $updated->items
                ->first()
                ->pharmacy_medicine_id,
        );

        $this->assertCount(
            1,
            $updated->attachments,
        );

        $this->assertSame(
            $path,
            $updated->attachments
                ->first()
                ->path,
        );

        $this->assertSame(
            2,
            $updated->activities()->count(),
        );
    }

    public function test_foreign_customer_is_rejected_and_transaction_rolls_back(): void
    {
        $contextA = $this->createContext('CUSTOMER-A');
        $contextB = $this->createContext('CUSTOMER-B');

        $data = $this->validData($contextA);

        $data['customer_id'] =
            $contextB['customer']->id;

        try {
            app(SavePrescriptionDraft::class)
                ->create(
                    actor: $contextA['assistant'],
                    data: $data,
                );

            $this->fail(
                'A foreign customer was accepted.',
            );
        } catch (ModelNotFoundException) {
            $this->assertTrue(true);
        }

        $this->assertDatabaseCount(
            'prescriptions',
            0,
        );

        $this->assertDatabaseCount(
            'prescription_activities',
            0,
        );
    }

    public function test_foreign_pharmacy_medicine_is_rejected_and_transaction_rolls_back(): void
    {
        $contextA = $this->createContext('MEDICINE-A');
        $contextB = $this->createContext('MEDICINE-B');

        $data = $this->validData($contextA);

        $data['items'][0]['pharmacy_medicine_id'] =
            $contextB['listing']->id;

        try {
            app(SavePrescriptionDraft::class)
                ->create(
                    actor: $contextA['assistant'],
                    data: $data,
                );

            $this->fail(
                'A foreign pharmacy medicine was accepted.',
            );
        } catch (ModelNotFoundException) {
            $this->assertTrue(true);
        }

        $this->assertDatabaseCount(
            'prescriptions',
            0,
        );

        $this->assertDatabaseCount(
            'prescription_items',
            0,
        );
    }

    public function test_uploaded_attachment_is_recorded_on_private_local_disk(): void
    {
        Storage::fake('local');

        $context = $this->createContext('FILE');

        $path = sprintf(
            'prescriptions/%d/secure-document.pdf',
            $context['pharmacy']->id,
        );

        Storage::disk('local')->put(
            $path,
            'private prescription content',
        );

        $data = $this->validData(
            $context,
            'uploaded',
        );

        $data['new_attachment_paths'] = [
            $path,
        ];

        $data['attachment_original_names'] = [
            $path => 'Doctor Prescription.pdf',
        ];

        $prescription = app(SavePrescriptionDraft::class)
            ->create(
                actor: $context['assistant'],
                data: $data,
            );

        $attachment = $prescription
            ->attachments
            ->first();

        $this->assertNotNull($attachment);

        $this->assertSame(
            'local',
            $attachment->disk,
        );

        $this->assertSame(
            $path,
            $attachment->path,
        );

        $this->assertSame(
            'Doctor Prescription.pdf',
            $attachment->original_name,
        );

        $this->assertTrue(
            Storage::disk('local')->exists($path),
        );
    }

    public function test_create_page_renders_readable_searchable_selectors(): void
    {
        $context = $this->createContext('INTERFACE');

        $this->actingAs($context['assistant'])
            ->get('/pharmacy/prescriptions/create')
            ->assertOk()
            ->assertSee('Customer / patient')
            ->assertSee(
                'Search by name, number, phone or email'
            )
            ->assertSee('Pharmacy branch')
            ->assertSee(
                'Medicine from pharmacy catalogue'
            )
            ->assertSee(
                'Search by medicine name or SKU'
            )
            ->assertSee(
                'Prescribed medicine name'
            );
    }

    private function createContext(
        string $suffix,
    ): array {
        $pharmacy = Pharmacy::create([
            'name' =>
                "{$suffix} Prescription Pharmacy",

            'phone' => '+257 61 '.random_int(
                100000,
                999999,
            ),

            'status' => 'approved',
        ]);

        $branch = PharmacyBranch::create([
            'pharmacy_id' => $pharmacy->id,
            'name' =>
                "{$suffix} Prescription Branch",

            'code' => 'DR-'.strtoupper(
                substr(md5($suffix), 0, 8),
            ),

            'is_main' => true,
            'status' => 'active',
        ]);

        $assistant = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $assistant->forceFill([
            'pharmacy_id' => $pharmacy->id,
            'pharmacy_branch_id' => $branch->id,
        ])->save();

        $assistant->assignRole(
            'pharmacy_assistant',
        );

        $customer = Customer::create([
            'pharmacy_id' => $pharmacy->id,
            'registered_branch_id' => $branch->id,
            'name' =>
                "{$suffix} Prescription Customer",

            'phone' => '+257 79 '.random_int(
                100000,
                999999,
            ),

            'email' =>
                strtolower($suffix)
                .'@customer.test',

            'status' => 'active',
        ]);

        $listing = $this->createListing(
            $pharmacy,
            "{$suffix} Test Medicine",
            $suffix,
        );

        return compact(
            'pharmacy',
            'branch',
            'assistant',
            'customer',
            'listing',
        );
    }

    private function createListing(
        Pharmacy $pharmacy,
        string $medicineName,
        string $suffix,
    ): PharmacyMedicine {
        $medicine = Medicine::create([
            'brand_name' => $medicineName,
            'approval_status' => 'approved',
            'is_active' => true,
        ]);

        return PharmacyMedicine::create([
            'pharmacy_id' => $pharmacy->id,
            'medicine_id' => $medicine->id,

            'sku' => 'RX-SKU-'.strtoupper(
                substr(md5($suffix), 0, 10),
            ),

            'selling_price' => 5000,
            'minimum_stock_level' => 0,
            'reorder_quantity' => 0,
            'expiry_warning_days' => 90,
            'alerts_enabled' => true,
            'status' => 'active',
        ])->load('medicine');
    }

    private function validData(
        array $context,
        string $source = 'manual',
    ): array {
        return [
            'customer_id' =>
                $context['customer']->id,

            'pharmacy_branch_id' =>
                $context['branch']->id,

            'source' => $source,
            'prescriber_name' =>
                'Dr Prescription Test',

            'prescriber_phone' =>
                '+257 71 000 000',

            'prescriber_facility' =>
                'Prescription Test Clinic',

            'prescriber_registration_number' =>
                'DR-TEST-001',

            'issued_at' =>
                today()->toDateString(),

            'valid_until' =>
                today()
                    ->addDays(30)
                    ->toDateString(),

            'notes' =>
                'Prescription draft test.',

            'items' => [
                [
                    'pharmacy_medicine_id' =>
                        $context['listing']->id,

                    'prescribed_name' =>
                        $context['listing']
                            ->medicine
                            ->brand_name,

                    'strength' => '500 mg',
                    'dosage_form' => 'Capsule',
                    'dosage' => '1 capsule',
                    'frequency' =>
                        'Three times daily',

                    'duration' => '7 days',
                    'quantity_prescribed' => 21,
                    'substitution_allowed' => false,
                    'instructions' =>
                        'Take after meals.',
                ],
            ],

            'new_attachment_paths' => [],
            'attachment_original_names' => [],
        ];
    }
}