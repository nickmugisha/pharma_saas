<?php

namespace Tests\Feature;

use App\Actions\Prescriptions\ManagePrescriptionWorkflow;
use App\Models\Customer;
use App\Models\Pharmacy;
use App\Models\PharmacyBranch;
use App\Models\Prescription;
use App\Models\PrescriptionAttachment;
use App\Models\PrescriptionItem;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PrescriptionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_complete_draft_can_be_submitted(): void
    {
        $context = $this->createContext('SUBMIT');

        $prescription = $this->createReadyPrescription(
            $context,
        );

        $result = app(ManagePrescriptionWorkflow::class)
            ->submit(
                $context['assistant'],
                $prescription,
            );

        $this->assertSame(
            Prescription::STATUS_SUBMITTED,
            $result->status,
        );

        $this->assertNotNull($result->submitted_at);

        $this->assertDatabaseHas(
            'prescription_activities',
            [
                'prescription_id' => $result->id,
                'activity_type' => 'submitted',
            ],
        );

        $this->assertDatabaseHas(
            'customer_activities',
            [
                'customer_id' => $result->customer_id,
                'activity_type' =>
                    'prescription_submitted',
            ],
        );
    }

    public function test_prescription_requires_items_before_submission(): void
    {
        $context = $this->createContext('NO-ITEM');

        $prescription = $this->createPrescription(
            $context,
        );

        PrescriptionAttachment::create([
            'prescription_id' => $prescription->id,
            'uploaded_by_user_id' =>
                $context['assistant']->id,

            'path' => 'prescriptions/test.pdf',
            'original_name' => 'test.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1000,
        ]);

        try {
            app(ManagePrescriptionWorkflow::class)
                ->submit(
                    $context['assistant'],
                    $prescription,
                );

            $this->fail(
                'A prescription without items was submitted.',
            );
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'items',
                $exception->errors(),
            );
        }

        $this->assertSame(
            Prescription::STATUS_DRAFT,
            $prescription->fresh()->status,
        );
    }

    public function test_uploaded_prescription_requires_attachment(): void
    {
        $context = $this->createContext('NO-FILE');

        $prescription = $this->createPrescription(
            $context,
        );

        $this->createItem($prescription);

        try {
            app(ManagePrescriptionWorkflow::class)
                ->submit(
                    $context['assistant'],
                    $prescription,
                );

            $this->fail(
                'An uploaded prescription without an attachment was submitted.',
            );
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'attachments',
                $exception->errors(),
            );
        }
    }

    public function test_pharmacist_can_review_and_approve(): void
    {
        $context = $this->createContext('APPROVE');

        $prescription = $this->createReadyPrescription(
            $context,
        );

        $workflow = app(
            ManagePrescriptionWorkflow::class,
        );

        $submitted = $workflow->submit(
            $context['assistant'],
            $prescription,
        );

        $reviewing = $workflow->startReview(
            $context['pharmacist'],
            $submitted,
        );

        $approved = $workflow->approve(
            $context['pharmacist'],
            $reviewing,
        );

        $this->assertSame(
            Prescription::STATUS_APPROVED,
            $approved->status,
        );

        $this->assertSame(
            $context['pharmacist']->id,
            $approved->reviewed_by_user_id,
        );

        $this->assertNotNull($approved->reviewed_at);
        $this->assertNotNull($approved->approved_at);

        $this->assertDatabaseHas(
            'prescription_activities',
            [
                'prescription_id' => $approved->id,
                'activity_type' => 'under_review',
            ],
        );

        $this->assertDatabaseHas(
            'prescription_activities',
            [
                'prescription_id' => $approved->id,
                'activity_type' => 'approved',
            ],
        );
    }

    public function test_pharmacist_rejection_requires_reason(): void
    {
        $context = $this->createContext('REJECT');

        $workflow = app(
            ManagePrescriptionWorkflow::class,
        );

        $prescription = $workflow->submit(
            $context['assistant'],
            $this->createReadyPrescription($context),
        );

        $prescription = $workflow->startReview(
            $context['pharmacist'],
            $prescription,
        );

        try {
            $workflow->reject(
                $context['pharmacist'],
                $prescription,
                ' ',
            );

            $this->fail(
                'A prescription was rejected without a reason.',
            );
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'rejection_reason',
                $exception->errors(),
            );
        }

        $rejected = $workflow->reject(
            $context['pharmacist'],
            $prescription,
            'The prescription information is incomplete.',
        );

        $this->assertSame(
            Prescription::STATUS_REJECTED,
            $rejected->status,
        );

        $this->assertSame(
            'The prescription information is incomplete.',
            $rejected->rejection_reason,
        );

        $this->assertNotNull($rejected->rejected_at);
    }

    public function test_workflow_enforces_permission_and_tenant_isolation(): void
    {
        $contextA = $this->createContext('TENANT-A');
        $contextB = $this->createContext('TENANT-B');

        $workflow = app(
            ManagePrescriptionWorkflow::class,
        );

        $foreignPrescription =
            $this->createReadyPrescription($contextB);

        try {
            $workflow->submit(
                $contextA['assistant'],
                $foreignPrescription,
            );

            $this->fail(
                'A foreign prescription was submitted.',
            );
        } catch (HttpException $exception) {
            $this->assertSame(
                403,
                $exception->getStatusCode(),
            );
        }

        $submitted = $workflow->submit(
            $contextB['assistant'],
            $foreignPrescription,
        );

        try {
            $workflow->startReview(
                $contextB['assistant'],
                $submitted,
            );

            $this->fail(
                'A user without validation permission started review.',
            );
        } catch (HttpException $exception) {
            $this->assertSame(
                403,
                $exception->getStatusCode(),
            );
        }
    }

    private function createContext(string $suffix): array
    {
        $pharmacy = Pharmacy::create([
            'name' => "{$suffix} Prescription Pharmacy",
            'phone' => '+257 61 '.random_int(
                100000,
                999999,
            ),
            'status' => 'approved',
        ]);

        $branch = PharmacyBranch::create([
            'pharmacy_id' => $pharmacy->id,
            'name' => "{$suffix} Prescription Branch",
            'code' => 'RX-'.strtoupper(
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
            'registered_branch_id' => $branch->id,
            'name' => "{$suffix} Prescription Customer",
            'phone' => '+257 79 '.random_int(
                100000,
                999999,
            ),
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
            'pharmacy_branch_id' => $branch->id,
        ])->save();

        $user->assignRole($role);

        return $user;
    }

    private function createPrescription(
        array $context,
    ): Prescription {
        return Prescription::create([
            'pharmacy_id' => $context['pharmacy']->id,
            'pharmacy_branch_id' =>
                $context['branch']->id,

            'customer_id' => $context['customer']->id,
            'created_by_user_id' =>
                $context['assistant']->id,

            'source' => 'uploaded',
            'prescriber_name' => 'Dr Test Prescriber',
            'prescriber_phone' => '+257 71 000 000',
            'prescriber_facility' => 'Test Clinic',
            'issued_at' => today(),
            'valid_until' => today()->addDays(30),
            'status' => Prescription::STATUS_DRAFT,
        ]);
    }

    private function createReadyPrescription(
        array $context,
    ): Prescription {
        $prescription = $this->createPrescription(
            $context,
        );

        $this->createItem($prescription);

        PrescriptionAttachment::create([
            'prescription_id' => $prescription->id,
            'uploaded_by_user_id' =>
                $context['assistant']->id,

            'path' => 'prescriptions/test.pdf',
            'original_name' =>
                'prescription-test.pdf',

            'mime_type' => 'application/pdf',
            'size_bytes' => 1000,
        ]);

        return $prescription;
    }

    private function createItem(
        Prescription $prescription,
    ): PrescriptionItem {
        return PrescriptionItem::create([
            'prescription_id' => $prescription->id,
            'prescribed_name' => 'Amoxicillin',
            'strength' => '500 mg',
            'dosage_form' => 'Capsule',
            'dosage' => '1 capsule',
            'frequency' => 'Three times daily',
            'duration' => '7 days',
            'quantity_prescribed' => 21,
            'quantity_dispensed' => 0,
            'instructions' => 'Take after meals.',
            'substitution_allowed' => false,
            'status' => 'pending',
        ]);
    }
}