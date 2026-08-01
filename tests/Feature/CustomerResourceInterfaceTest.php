<?php

namespace Tests\Feature;

use App\Actions\Customers\RecordCustomerActivity;
use App\Models\Customer;
use App\Models\PatientProfile;
use App\Models\Pharmacy;
use App\Models\PharmacyBranch;
use App\Models\Sale;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerResourceInterfaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_owner_only_sees_own_pharmacy_customers(): void
    {
        $contextA = $this->createContext('ALPHA');
        $contextB = $this->createContext('BRAVO');

        $customerA = $this->createCustomer(
            $contextA,
            'Alpha Customer',
        );

        $customerB = $this->createCustomer(
            $contextB,
            'Bravo Foreign Customer',
        );

        $this->actingAs($contextA['owner'])
            ->get('/pharmacy/customers')
            ->assertOk()
            ->assertSee($customerA->name)
            ->assertDontSee($customerB->name);
    }

    public function test_customer_view_displays_profile_sales_and_activity(): void
    {
        $context = $this->createContext('PROFILE');

        $customer = $this->createCustomer(
            $context,
            'Profile Customer',
        );

        PatientProfile::create([
            'customer_id' => $customer->id,
            'created_by_user_id' => $context['owner']->id,
            'date_of_birth' => '1994-07-16',
            'sex' => 'female',
            'emergency_contact_name' =>
                'Emergency Contact Profile',
            'emergency_contact_phone' =>
                '+257 79 123 456',
            'emergency_contact_relation' =>
                'Sibling',
        ]);

        $sale = Sale::create([
            'pharmacy_id' => $context['pharmacy']->id,
            'pharmacy_branch_id' => $context['branch']->id,
            'cashier_user_id' => $context['owner']->id,
            'customer_id' => $customer->id,
            'sale_number' => 'CUS-SALE-PROFILE',
            'receipt_number' => 'CUS-RCT-PROFILE',
            'channel' => 'pos',
            'sold_at' => now()->subDay(),
            'completed_at' => now()->subDay(),
            'status' => 'completed',
            'payment_status' => 'paid',
            'currency' => 'BIF',
            'subtotal' => 15000,
            'discount_total' => 0,
            'tax_total' => 0,
            'grand_total' => 15000,
            'paid_amount' => 15000,
            'change_amount' => 0,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
        ]);

        app(RecordCustomerActivity::class)->handle(
            actor: $context['owner'],
            customer: $customer,
            activityType: 'customer_test_activity',
            title: 'Customer profile activity recorded',
            description:
                'Activity created for the customer interface test.',
            subject: $sale,
        );

        $this->actingAs($context['owner'])
            ->get("/pharmacy/customers/{$customer->id}")
            ->assertOk()
            ->assertSee($customer->customer_number)
            ->assertSee($customer->name)
            ->assertSee('Emergency Contact Profile')
            ->assertSee('CUS-RCT-PROFILE')
            ->assertSee('Customer profile activity recorded')
            ->assertSee('15 000 BIF');
    }

    public function test_foreign_customer_cannot_be_opened_directly(): void
    {
        $contextA = $this->createContext('TENANT-A');
        $contextB = $this->createContext('TENANT-B');

        $foreignCustomer = $this->createCustomer(
            $contextB,
            'Foreign Customer',
        );

        $this->actingAs($contextA['owner'])
            ->get(
                "/pharmacy/customers/{$foreignCustomer->id}"
            )
            ->assertNotFound();

        $this->actingAs($contextA['owner'])
            ->get(
                "/pharmacy/customers/{$foreignCustomer->id}/edit"
            )
            ->assertNotFound();
    }

    public function test_view_only_user_cannot_create_or_edit_customer(): void
    {
        $context = $this->createContext('READONLY');

        $customer = $this->createCustomer(
            $context,
            'Read Only Customer',
        );

        $viewer = $this->createPharmacyUser(
            $context,
            'delivery_coordinator',
        );

        $this->assertTrue(
            $viewer->can('customers.view'),
        );

        $this->assertFalse(
            $viewer->can('customers.manage'),
        );

        $this->actingAs($viewer)
            ->get('/pharmacy/customers')
            ->assertOk();

        $this->actingAs($viewer)
            ->get("/pharmacy/customers/{$customer->id}")
            ->assertOk();

        $this->actingAs($viewer)
            ->get('/pharmacy/customers/create')
            ->assertForbidden();

        $this->actingAs($viewer)
            ->get(
                "/pharmacy/customers/{$customer->id}/edit"
            )
            ->assertForbidden();
    }

    public function test_user_without_customer_permission_is_forbidden(): void
    {
        $context = $this->createContext('DENIED');

        $user = $this->createPharmacyUser(
            $context,
            'stock_manager',
        );

        $this->assertFalse(
            $user->can('customers.view'),
        );

        $this->actingAs($user)
            ->get('/pharmacy/customers')
            ->assertForbidden();
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
            'code' => 'CR-'.strtoupper(
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

    private function createCustomer(
        array $context,
        string $name,
    ): Customer {
        return Customer::create([
            'pharmacy_id' => $context['pharmacy']->id,
            'registered_branch_id' => $context['branch']->id,
            'name' => $name,
            'phone' => '+257 79 '.random_int(
                100000,
                999999,
            ),
            'email' => uniqid(
                'customer_',
                true,
            ).'@example.test',
            'status' => 'active',
        ]);
    }

    private function createPharmacyUser(
        array $context,
        string $role,
    ): User {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $user->forceFill([
            'pharmacy_id' => $context['pharmacy']->id,
            'pharmacy_branch_id' => $context['branch']->id,
        ])->save();

        $user->assignRole($role);

        return $user;
    }
}