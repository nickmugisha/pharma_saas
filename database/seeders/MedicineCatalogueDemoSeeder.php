<?php

namespace Database\Seeders;

use App\Models\DosageForm;
use App\Models\Manufacturer;
use App\Models\Medicine;
use App\Models\MedicineCategory;
use App\Models\Molecule;
use App\Models\Pharmacy;
use App\Models\PharmacyMedicine;
use Illuminate\Database\Seeder;

class MedicineCatalogueDemoSeeder extends Seeder
{
    public function run(): void
    {
        $molecule = Molecule::updateOrCreate(
            ['name' => 'Paracetamol'],
            [
                'description' =>
                    'Analgesic and antipyretic active ingredient.',
                'is_active' => true,
            ],
        );

        $category = MedicineCategory::updateOrCreate(
            ['name' => 'Analgesics and Antipyretics'],
            [
                'description' =>
                    'Medicines used to relieve pain and reduce fever.',
                'is_active' => true,
            ],
        );

        $dosageForm = DosageForm::updateOrCreate(
            ['name' => 'Tablet'],
            [
                'abbreviation' => 'TAB',
                'description' =>
                    'Solid oral pharmaceutical dosage form.',
                'is_active' => true,
            ],
        );

        $manufacturer = Manufacturer::updateOrCreate(
            ['name' => 'PharmaLab Burundi'],
            [
                'country' => 'Burundi',
                'email' => 'contact@pharmalab.bi',
                'phone' => '+257 61 00 10 20',
                'address' => 'Bujumbura, Burundi',
                'is_active' => true,
            ],
        );

        $medicine = Medicine::updateOrCreate(
            ['barcode' => 'DEMO-PARA-500-20'],
            [
                'brand_name' => 'Paracetamol PharmaLab 500',
                'generic_name' => 'Paracetamol',
                'medicine_category_id' => $category->id,
                'dosage_form_id' => $dosageForm->id,
                'manufacturer_id' => $manufacturer->id,
                'strength' => '500 mg',
                'package_size' => 'Box of 20 tablets',
                'regulatory_code' => 'ABREMA-DEMO-PARA-001',
                'description' =>
                    'Oral tablet containing 500 mg of paracetamol.',
                'indications' =>
                    'Temporary relief of mild to moderate pain and fever.',
                'storage_instructions' =>
                    'Store below 30°C in a dry place.',
                'prescription_status' => 'otc',
                'approval_status' => 'approved',
                'reviewed_at' => now(),
                'is_active' => true,
            ],
        );

        $medicine->ingredients()->updateOrCreate(
            ['molecule_id' => $molecule->id],
            [
                'strength' => '500 mg',
                'is_primary' => true,
                'sort_order' => 0,
            ],
        );

        $pharmacy = Pharmacy::query()
            ->where('license_number', 'PH-BDI-DEMO-001')
            ->first();

        if ($pharmacy) {
            PharmacyMedicine::updateOrCreate(
                [
                    'pharmacy_id' => $pharmacy->id,
                    'medicine_id' => $medicine->id,
                ],
                [
                    'internal_sku' => 'PARA-500-TAB',
                    'selling_price' => 3500,
                    'online_price' => 3300,
                    'currency' => 'BIF',
                    'pharmacy_description' =>
                        'Paracetamol 500 mg tablets available at Pharmacie Centrale Bujumbura.',
                    'is_available' => true,
                    'is_visible_online' => true,
                    'status' => 'active',
                ],
            );
        }

        $this->command?->info(
            'Demo medicine catalogue and pharmacy listing are ready.',
        );
    }
}