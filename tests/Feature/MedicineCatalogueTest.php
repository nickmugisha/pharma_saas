<?php

namespace Tests\Feature;

use App\Models\DosageForm;
use App\Models\Manufacturer;
use App\Models\Medicine;
use App\Models\MedicineCategory;
use App\Models\Molecule;
use App\Models\Pharmacy;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MedicineCatalogueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_medicine_has_catalogue_relations_ingredients_and_primary_image(): void
    {
        $category = MedicineCategory::create([
            'name' => 'Analgesics',
            'is_active' => true,
        ]);

        $dosageForm = DosageForm::create([
            'name' => 'Tablet',
            'abbreviation' => 'TAB',
            'is_active' => true,
        ]);

        $manufacturer = Manufacturer::create([
            'name' => 'Test Manufacturer',
            'country' => 'Burundi',
            'is_active' => true,
        ]);

        $molecule = Molecule::create([
            'name' => 'Paracetamol',
            'is_active' => true,
        ]);

        $medicine = Medicine::create([
            'brand_name' => 'Paracetamol Test 500',
            'generic_name' => 'Paracetamol',
            'medicine_category_id' => $category->id,
            'dosage_form_id' => $dosageForm->id,
            'manufacturer_id' => $manufacturer->id,
            'strength' => '500 mg',
            'prescription_status' => 'otc',
            'approval_status' => 'draft',
        ]);

        $medicine->ingredients()->create([
            'molecule_id' => $molecule->id,
            'strength' => '500 mg',
            'is_primary' => true,
            'sort_order' => 0,
        ]);

        $firstImage = $medicine->images()->create([
            'path' => 'medicines/first-image.jpg',
            'alt_text' => 'First medicine image',
            'is_primary' => true,
            'sort_order' => 0,
        ]);

        $secondImage = $medicine->images()->create([
            'path' => 'medicines/second-image.jpg',
            'alt_text' => 'Second medicine image',
            'is_primary' => true,
            'sort_order' => 1,
        ]);

        $medicine->refresh();

        $this->assertNotNull($medicine->uuid);
        $this->assertNotNull($medicine->slug);
        $this->assertSame($category->id, $medicine->category->id);
        $this->assertSame($dosageForm->id, $medicine->dosageForm->id);
        $this->assertSame($manufacturer->id, $medicine->manufacturer->id);
        $this->assertCount(1, $medicine->ingredients);
        $this->assertSame('Paracetamol', $medicine->ingredients->first()->molecule->name);

        $this->assertFalse($firstImage->fresh()->is_primary);
        $this->assertTrue($secondImage->fresh()->is_primary);
        $this->assertSame(
            $secondImage->id,
            $medicine->fresh()->primaryImage->id,
        );
    }

    public function test_submission_and_review_statuses_set_timestamps(): void
    {
        $medicine = Medicine::create([
            'brand_name' => 'Approval Workflow Medicine',
            'approval_status' => 'pending_review',
        ]);

        $this->assertNotNull($medicine->submitted_at);
        $this->assertNull($medicine->reviewed_at);

        $medicine->update([
            'approval_status' => 'approved',
        ]);

        $this->assertNotNull($medicine->fresh()->reviewed_at);
        $this->assertSame(
            'approved',
            $medicine->fresh()->approval_status,
        );
    }

    public function test_super_admin_can_manage_catalogue_but_pharmacy_owner_cannot_access_super_admin_resource(): void
    {
        $superAdmin = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $superAdmin->assignRole('super_admin');

        $this->actingAs($superAdmin)
            ->get('/super-admin/medicines')
            ->assertOk();

        $this->actingAs($superAdmin)
            ->get('/super-admin/medicines/create')
            ->assertOk();

        $pharmacy = Pharmacy::create([
            'name' => 'Test Pharmacy',
            'phone' => '+257 61 00 20 30',
            'status' => 'approved',
        ]);

        $pharmacyOwner = User::factory()->create([
            'email_verified_at' => now(),
            'is_active' => true,
            'pharmacy_id' => $pharmacy->id,
        ]);

        $pharmacyOwner->assignRole('pharmacy_owner');

        $this->actingAs($pharmacyOwner)
            ->get('/super-admin/medicines')
            ->assertForbidden();
    }
}