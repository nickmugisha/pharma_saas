<?php

namespace Tests\Feature;

use App\Models\Medicine;
use App\Models\MedicineImage;
use App\Models\Pharmacy;
use App\Models\PharmacyMedicine;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\QueryException;
use Tests\TestCase;

class PharmacyMedicineIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_pharmacy_owner_only_sees_and_edits_own_medicine_listings(): void
    {
        $pharmacyA = $this->createPharmacy(
            'Pharmacy A',
            '+257 61 00 30 01',
        );

        $pharmacyB = $this->createPharmacy(
            'Pharmacy B',
            '+257 61 00 30 02',
        );

        $ownerA = $this->createOwner(
            'owner-a@example.test',
            $pharmacyA,
        );

        $medicineA = Medicine::create([
            'brand_name' => 'Medicine Belonging To Pharmacy A',
            'approval_status' => 'approved',
            'is_active' => true,
        ]);

        $medicineB = Medicine::create([
            'brand_name' => 'Medicine Belonging To Pharmacy B',
            'approval_status' => 'approved',
            'is_active' => true,
        ]);

        $listingA = PharmacyMedicine::create([
            'pharmacy_id' => $pharmacyA->id,
            'medicine_id' => $medicineA->id,
            'created_by_user_id' => $ownerA->id,
            'internal_sku' => 'MED-A',
            'selling_price' => 3500,
            'online_price' => 3300,
            'is_available' => true,
            'is_visible_online' => true,
            'status' => 'active',
        ]);

        $listingB = PharmacyMedicine::create([
            'pharmacy_id' => $pharmacyB->id,
            'medicine_id' => $medicineB->id,
            'internal_sku' => 'MED-B',
            'selling_price' => 4000,
            'status' => 'active',
        ]);

        $this->actingAs($ownerA)
            ->get('/pharmacy/pharmacy-medicines')
            ->assertOk()
            ->assertSee('Medicine Belonging To Pharmacy A')
            ->assertDontSee('Medicine Belonging To Pharmacy B');

        $this->actingAs($ownerA)
            ->get("/pharmacy/pharmacy-medicines/{$listingA->id}/edit")
            ->assertOk();

        $this->actingAs($ownerA)
            ->get("/pharmacy/pharmacy-medicines/{$listingB->id}/edit")
            ->assertNotFound();
    }

    public function test_pharmacy_listing_uses_central_medicine_picture_and_prices(): void
    {
        $pharmacy = $this->createPharmacy(
            'Picture Test Pharmacy',
            '+257 61 00 30 03',
        );

        $owner = $this->createOwner(
            'picture-owner@example.test',
            $pharmacy,
        );

        $medicine = Medicine::create([
            'brand_name' => 'Paracetamol Picture Test',
            'generic_name' => 'Paracetamol',
            'strength' => '500 mg',
            'approval_status' => 'approved',
            'is_active' => true,
        ]);

        MedicineImage::create([
            'medicine_id' => $medicine->id,
            'path' => 'medicines/paracetamol-test.jpg',
            'alt_text' => 'Paracetamol medicine package',
            'is_primary' => true,
        ]);

        $listing = PharmacyMedicine::create([
            'pharmacy_id' => $pharmacy->id,
            'medicine_id' => $medicine->id,
            'created_by_user_id' => $owner->id,
            'selling_price' => 3500,
            'online_price' => 3300,
            'currency' => 'BIF',
            'is_available' => true,
            'is_visible_online' => true,
            'status' => 'active',
        ]);

        $listing->load('medicine.primaryImage');

        $this->assertSame(
            'Paracetamol Picture Test',
            $listing->medicine->brand_name,
        );

        $this->assertSame(
            'medicines/paracetamol-test.jpg',
            $listing->medicine->primaryImage->path,
        );

        $this->assertSame('3500.00', $listing->selling_price);
        $this->assertSame('3300.00', $listing->online_price);
        $this->assertTrue($listing->is_visible_online);
    }

    public function test_same_medicine_cannot_be_added_twice_to_one_pharmacy(): void
    {
        $pharmacy = $this->createPharmacy(
            'Duplicate Test Pharmacy',
            '+257 61 00 30 04',
        );

        $medicine = Medicine::create([
            'brand_name' => 'Duplicate Test Medicine',
            'approval_status' => 'approved',
            'is_active' => true,
        ]);

        PharmacyMedicine::create([
            'pharmacy_id' => $pharmacy->id,
            'medicine_id' => $medicine->id,
            'selling_price' => 1000,
        ]);

        $this->expectException(QueryException::class);

        PharmacyMedicine::create([
            'pharmacy_id' => $pharmacy->id,
            'medicine_id' => $medicine->id,
            'selling_price' => 1200,
        ]);
    }

    private function createPharmacy(
        string $name,
        string $phone,
    ): Pharmacy {
        return Pharmacy::create([
            'name' => $name,
            'phone' => $phone,
            'status' => 'approved',
        ]);
    }

    private function createOwner(
        string $email,
        Pharmacy $pharmacy,
    ): User {
        $owner = User::factory()->create([
            'email' => $email,
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $owner->forceFill([
            'pharmacy_id' => $pharmacy->id,
        ])->save();

        $owner->assignRole('pharmacy_owner');

        return $owner;
    }
}