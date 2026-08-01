<?php

namespace Tests\Feature;

use App\Actions\Customers\RecordCustomerActivity;
use App\Models\Customer;
use App\Models\PatientProfile;
use App\Models\Pharmacy;
use App\Models\PharmacyBranch;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CustomerDomainTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_customer_receives_number_and_optional_patient_profile(): void
    {
        $context = $this->createContext('PROFILE');

        $customer = $this->createCustomer($context);

        $profile = PatientProfile::create([
            'customer_id' => $customer->id,
            'created_by_user_id' => $context['owner']->id,
            'date_of_birth' => '1995-06-15',
            'sex' => 'female',
            'emergency_contact_name' => 'Emergency Contact',
            'emergency_contact_phone' => '+257 79 000 001',
            'emergency_contact_relation' => 'Sibling',
        ]);

        $this->assertNotNull($customer->uuid);
        $this->assertStringStartsWith(
            'CUS-',
            $customer->customer_number,
        );
        $this->assertNotNull($customer->registered_at);

        $this->assertSame(
            $profile->id,
            $customer->patientProfile->id,
        );

        $this->assertSame(
            $customer->id,
            $profile->customer->id,
        );
    }

    public function test_activity_is_recorded_and_updates_customer_timeline(): void
    {
        $context = $this->createContext('ACTIVITY');

        $customer = $this->createCustomer($context);

        $activity = app(RecordCustomerActivity::class)
            ->handle(
                actor: $context['owner'],
                customer: $customer,
                activityType: 'customer_registered',
                title: 'Customer account registered',
                description:
                    'The customer account was created at the branch.',
                subject: $customer,
                metadata: [
                    'source' => 'pharmacy_panel',
                ],
            );

        $this->assertSame(
            'customer_registered',
            $activity->activity_type,
        );

        $this->assertSame(
            $customer->id,
            $activity->customer_id,
        );

        $this->assertSame(
            $context['branch']->id,
            $activity->pharmacy_branch_id,
        );

        $this->assertSame(
            'pharmacy_panel',
            $activity->metadata['source'],
        );

        $this->assertNotNull(
            $customer->fresh()->last_activity_at,
        );
    }

    public function test_actor_cannot_record_activity_for_foreign_customer(): void
    {
        $contextA = $this->createContext('TENANT-A');
        $contextB = $this->createContext('TENANT-B');

        $customerB = $this->createCustomer($contextB);

        try {
            app(RecordCustomerActivity::class)->handle(
                actor: $contextA['owner'],
                customer: $customerB,
                activityType: 'manual_note',
                title: 'Invalid foreign activity',
            );

            $this->fail(
                'A cross-pharmacy customer activity was accepted.'
            );
        } catch (HttpException $exception) {
            $this->assertSame(
                403,
                $exception->getStatusCode(),
            );
        }

        $this->assertDatabaseCount(
            'customer_activities',
            0,
        );
    }

    public function test_customer_activity_cannot_be_modified(): void
    {
        $context = $this->createContext('IMMUTABLE');

        $customer = $this->createCustomer($context);

        $activity = app(RecordCustomerActivity::class)
            ->handle(
                actor: $context['owner'],
                customer: $customer,
                activityType: 'manual_note',
                title: 'Original activity title',
            );

        $this->expectException(LogicException::class);

        $activity->update([
            'title' => 'Changed activity title',
        ]);
    }

    private function createContext(string $suffix): array
    {
        $pharmacy = Pharmacy::create([
            'name' => "{$suffix} Customer Pharmacy",
            'phone' => '+257 61 '.random_int(
                100000,
                999999,
            ),
            'status' => 'approved',
        ]);

        $branch = PharmacyBranch::create([
            'pharmacy_id' => $pharmacy->id,
            'name' => "{$suffix} Customer Branch",
            'code' => 'CUS-'.strtoupper(
                substr(md5($suffix), 0, 8),
            ),
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

        return compact(
            'pharmacy',
            'branch',
            'owner',
        );
    }

    private function createCustomer(array $context): Customer
    {
        return Customer::create([
            'pharmacy_id' => $context['pharmacy']->id,
            'registered_branch_id' => $context['branch']->id,
            'name' => 'Test Customer',
            'phone' => '+257 79 '.random_int(
                100000,
                999999,
            ),
            'email' => uniqid('customer_', true).'@example.test',
            'status' => 'active',
        ]);
    }
}