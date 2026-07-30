<?php

namespace Tests\Feature;

use App\Models\Pharmacy;
use App\Models\PharmacyBranch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerPharmacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_pharmacy_receives_uuid_and_approval_timestamp(): void
    {
        $pharmacy = Pharmacy::create([
            'name' => 'Test Partner Pharmacy',
            'phone' => '+257 61 00 00 10',
            'status' => 'approved',
        ]);

        $this->assertNotNull($pharmacy->uuid);
        $this->assertNotNull($pharmacy->approved_at);
        $this->assertSame('approved', $pharmacy->status);
    }

    public function test_a_pharmacy_can_only_have_one_main_branch(): void
    {
        $pharmacy = Pharmacy::create([
            'name' => 'Branch Test Pharmacy',
            'phone' => '+257 61 00 00 11',
            'status' => 'approved',
        ]);

        $firstBranch = PharmacyBranch::create([
            'pharmacy_id' => $pharmacy->id,
            'name' => 'First Main Branch',
            'code' => 'MAIN-1',
            'is_main' => true,
            'status' => 'active',
        ]);

        $secondBranch = PharmacyBranch::create([
            'pharmacy_id' => $pharmacy->id,
            'name' => 'Second Main Branch',
            'code' => 'MAIN-2',
            'is_main' => true,
            'status' => 'active',
        ]);

        $this->assertFalse($firstBranch->fresh()->is_main);
        $this->assertTrue($secondBranch->fresh()->is_main);
    }
}