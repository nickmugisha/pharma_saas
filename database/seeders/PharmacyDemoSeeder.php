<?php

namespace Database\Seeders;

use App\Models\Pharmacy;
use App\Models\PharmacyBranch;
use App\Models\User;
use Illuminate\Database\Seeder;

class PharmacyDemoSeeder extends Seeder
{
    public function run(): void
    {
        $pharmacy = Pharmacy::updateOrCreate(
            [
                'license_number' => 'PH-BDI-DEMO-001',
            ],
            [
                'name' => 'Pharmacie Centrale Bujumbura',
                'legal_name' => 'Pharmacie Centrale Bujumbura SPRL',
                'registration_number' => 'RC-DEMO-2026-001',
                'tax_number' => 'NIF-DEMO-2026-001',
                'email' => 'contact@pharmaciecentrale.bi',
                'phone' => '+257 61 04 68 63',
                'alternate_phone' => '+257 79 00 11 22',
                'address' => 'Avenue de la Mission, Rohero, Bujumbura, Burundi',
                'city' => 'Bujumbura',
                'province' => 'Bujumbura Mairie',
                'country' => 'Burundi',
                'status' => 'approved',
                'notes' => 'Pharmacie partenaire de démonstration.',
            ],
        );

        $branch = PharmacyBranch::updateOrCreate(
            [
                'pharmacy_id' => $pharmacy->id,
                'code' => 'HQ',
            ],
            [
                'name' => 'Bujumbura Main Branch',
                'is_main' => true,
                'status' => 'active',
                'email' => 'contact@pharmaciecentrale.bi',
                'phone' => '+257 61 04 68 63',
                'address' => 'Avenue de la Mission, Rohero',
                'city' => 'Bujumbura',
                'province' => 'Bujumbura Mairie',
            ],
        );

        $owner = User::query()
            ->where('email', 'pharmacy.owner@pharma-saas.local')
            ->first();

        if ($owner) {
            $owner->forceFill([
                'pharmacy_id' => $pharmacy->id,
                'pharmacy_branch_id' => $branch->id,
            ])->save();
        }

        $this->command?->info(
            'Demo pharmacy, branch and owner association are ready.',
        );
    }
}