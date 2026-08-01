<?php

namespace Tests\Feature;

use App\Actions\Prescriptions\ManagePrescriptionWorkflow;
use App\Actions\Prescriptions\RecordPrescriptionActivity;
use App\Models\Customer;
use App\Models\Pharmacy;
use App\Models\PharmacyBranch;
use App\Models\Prescription;
use App\Models\PrescriptionAttachment;
use App\Models\PrescriptionItem;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PrescriptionResourceInterfaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');
    }

    public function test_user_only_sees_own_pharmacy_prescriptions_and_full_profile(): void
    {
        $contextA = $this->createContext('ALPHA');
        $contextB = $this->createContext('BRAVO');

        $prescriptionA = $this->createReadyPrescription(
            $contextA,
            'Alpha Test Medicine',
        );

        $prescriptionB = $this->createReadyPrescription(
            $contextB,
            'Bravo Foreign Medicine',
        );

        app(RecordPrescriptionActivity::class)->handle(
            actor: $contextA['assistant'],
            prescription: $prescriptionA,
            activityType: 'interface_test',
            title: 'Prescription interface activity',
            description:
                'Activity created for the prescription profile test.',
        );

        $this->actingAs($contextA['assistant'])
            ->get('/pharmacy/prescriptions')
            ->assertOk()
            ->assertSee(
                $prescriptionA->prescription_number,
            )
            ->assertDontSee(
                $prescriptionB->prescription_number,
            );

        $this->actingAs($contextA['assistant'])
            ->get(
                "/pharmacy/prescriptions/{$prescriptionA->id}",
            )
            ->assertOk()
            ->assertSee(
                $prescriptionA->prescription_number,
            )
            ->assertSee(
                $contextA['customer']->name,
            )
            ->assertSee('Alpha Test Medicine')
            ->assertSee('Dr Interface Test')
            ->assertSee('Interface Test Clinic')
            ->assertSee('Doctor Prescription.pdf')
            ->assertSee(
                'Prescription interface activity',
            );
    }

    public function test_foreign_prescription_cannot_be_opened_directly(): void
    {
        $contextA = $this->createContext('TENANT-A');
        $contextB = $this->createContext('TENANT-B');

        $foreignPrescription =
            $this->createReadyPrescription(
                $contextB,
                'Foreign Medicine',
            );

        $this->actingAs($contextA['assistant'])
            ->get(
                "/pharmacy/prescriptions/{$foreignPrescription->id}",
            )
            ->assertNotFound();

        $this->actingAs($contextA['assistant'])
            ->get(
                "/pharmacy/prescriptions/{$foreignPrescription->id}/edit",
            )
            ->assertNotFound();
    }

    public function test_workflow_buttons_follow_status_and_permission(): void
    {
        $context = $this->createContext('ACTIONS');

        $prescription = $this->createReadyPrescription(
            $context,
            'Workflow Medicine',
        );

        $this->actingAs($context['assistant'])
            ->get(
                "/pharmacy/prescriptions/{$prescription->id}",
            )
            ->assertOk()
            ->assertSee('Submit for review')
            ->assertDontSee('Start review')
            ->assertDontSee('Approve');

        $workflow = app(
            ManagePrescriptionWorkflow::class,
        );

        $submitted = $workflow->submit(
            $context['assistant'],
            $prescription,
        );

        $this->actingAs($context['assistant'])
            ->get(
                "/pharmacy/prescriptions/{$submitted->id}",
            )
            ->assertOk()
            ->assertDontSee('Submit for review')
            ->assertDontSee('Start review');

        $this->actingAs($context['pharmacist'])
            ->get(
                "/pharmacy/prescriptions/{$submitted->id}",
            )
            ->assertOk()
            ->assertSee('Start review')
            ->assertDontSee('Approve');

        $reviewing = $workflow->startReview(
            $context['pharmacist'],
            $submitted,
        );

        $this->actingAs($context['pharmacist'])
            ->get(
                "/pharmacy/prescriptions/{$reviewing->id}",
            )
            ->assertOk()
            ->assertSee('Approve')
            ->assertSee('Reject');
    }

    public function test_authorized_user_can_download_private_attachment(): void
    {
        $context = $this->createContext('DOWNLOAD');

        $prescription = $this->createReadyPrescription(
            $context,
            'Download Medicine',
        );

        $attachment = $prescription
            ->attachments
            ->first();

        $this->actingAs($context['assistant'])
            ->get(
                route(
                    'pharmacy.prescription-attachments.download',
                    [
                        'attachment' => $attachment,
                    ],
                ),
            )
            ->assertOk()
            ->assertDownload(
                'Doctor Prescription.pdf',
            );
    }

    public function test_attachment_download_enforces_permission_and_tenant_isolation(): void
    {
        $contextA = $this->createContext('FILE-A');
        $contextB = $this->createContext('FILE-B');

        $prescriptionA = $this->createReadyPrescription(
            $contextA,
            'Protected Medicine',
        );

        $attachment = $prescriptionA
            ->attachments
            ->first();

        $foreignUrl = route(
            'pharmacy.prescription-attachments.download',
            [
                'attachment' => $attachment,
            ],
        );

        $this->actingAs($contextB['assistant'])
            ->get($foreignUrl)
            ->assertNotFound();

        $stockManager = $this->createUser(
            $contextA['pharmacy'],
            $contextA['branch'],
            'stock_manager',
        );

        $this->assertFalse(
            $stockManager->can('prescriptions.view'),
        );

        $this->actingAs($stockManager)
            ->get($foreignUrl)
            ->assertForbidden();
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

            'code' => 'PI-'.strtoupper(
                substr(md5($suffix), 0, 8),
            ),

            'is_main' => true,
            'status' => 'active',
        ]);

        $assistant = $this->createUser(
            $pharmacy,
            $branch,
            'pharmacy_assistant',
        );

        $pharmacist = $this->createUser(
            $pharmacy,
            $branch,
            'pharmacist',
        );

        $customer = Customer::create([
            'pharmacy_id' => $pharmacy->id,

            'registered_branch_id' =>
                $branch->id,

            'name' =>
                "{$suffix} Prescription Customer",

            'phone' => '+257 79 '.random_int(
                100000,
                999999,
            ),

            'email' =>
                strtolower($suffix)
                .'@prescription.test',

            'status' => 'active',
        ]);

        return compact(
            'pharmacy',
            'branch',
            'assistant',
            'pharmacist',
            'customer',
        );
    }

    private function createUser(
        Pharmacy $pharmacy,
        PharmacyBranch $branch,
        string $role,
    ): User {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $user->forceFill([
            'pharmacy_id' => $pharmacy->id,

            'pharmacy_branch_id' =>
                $branch->id,
        ])->save();

        $user->assignRole($role);

        return $user;
    }

    private function createReadyPrescription(
        array $context,
        string $medicineName,
    ): Prescription {
        $prescription = Prescription::create([
            'pharmacy_id' =>
                $context['pharmacy']->id,

            'pharmacy_branch_id' =>
                $context['branch']->id,

            'customer_id' =>
                $context['customer']->id,

            'created_by_user_id' =>
                $context['assistant']->id,

            'source' => 'uploaded',

            'prescriber_name' =>
                'Dr Interface Test',

            'prescriber_phone' =>
                '+257 71 000 000',

            'prescriber_facility' =>
                'Interface Test Clinic',

            'prescriber_registration_number' =>
                'DR-INTERFACE-001',

            'issued_at' => today(),

            'valid_until' =>
                today()->addDays(30),

            'status' =>
                Prescription::STATUS_DRAFT,
        ]);

        PrescriptionItem::create([
            'prescription_id' =>
                $prescription->id,

            'prescribed_name' =>
                $medicineName,

            'strength' => '500 mg',
            'dosage_form' => 'Tablet',
            'dosage' => '1 tablet',
            'frequency' => 'Twice daily',
            'duration' => '7 days',
            'quantity_prescribed' => 14,
            'quantity_dispensed' => 0,
            'instructions' =>
                'Take after meals.',
            'substitution_allowed' => false,
            'status' => 'pending',
        ]);

        $path = sprintf(
            'prescriptions/%d/%s.pdf',
            $context['pharmacy']->id,
            strtolower(
                substr(
                    md5($medicineName),
                    0,
                    12,
                ),
            ),
        );

        Storage::disk('local')->put(
            $path,
            'private prescription document',
        );

        PrescriptionAttachment::create([
            'prescription_id' =>
                $prescription->id,

            'uploaded_by_user_id' =>
                $context['assistant']->id,

            'attachment_type' =>
                'prescription',

            'disk' => 'local',
            'path' => $path,

            'original_name' =>
                'Doctor Prescription.pdf',

            'mime_type' =>
                'application/pdf',

            'size_bytes' =>
                Storage::disk('local')->size(
                    $path,
                ),
        ]);

        return $prescription->fresh([
            'items',
            'attachments',
            'customer',
            'branch',
        ]);
    }
}