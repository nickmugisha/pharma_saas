<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class MarketplaceDemoExpansionSeeder extends Seeder
{
    /** @var array<string, array<int, string>> */
    private array $columnCache = [];

    public function run(): void
    {
        $tables = [
            'pharmacies' => $this->tableFor(\App\Models\Pharmacy::class, 'pharmacies'),
            'branches' => $this->tableFor(\App\Models\PharmacyBranch::class, 'pharmacy_branches'),
            'users' => $this->tableFor(\App\Models\User::class, 'users'),
            'medicines' => $this->tableFor(\App\Models\Medicine::class, 'medicines'),
            'categories' => $this->tableFor(\App\Models\MedicineCategory::class, 'medicine_categories'),
            'dosage_forms' => $this->tableFor(\App\Models\DosageForm::class, 'dosage_forms'),
            'manufacturers' => $this->tableFor(\App\Models\Manufacturer::class, 'manufacturers'),
            'molecules' => $this->tableFor(\App\Models\Molecule::class, 'molecules'),
            'ingredients' => $this->tableFor(\App\Models\MedicineIngredient::class, 'medicine_ingredients'),
            'images' => $this->tableFor(\App\Models\MedicineImage::class, 'medicine_images'),
            'listings' => $this->tableFor(\App\Models\PharmacyMedicine::class, 'pharmacy_medicines'),
            'batches' => $this->tableFor(\App\Models\MedicineBatch::class, 'medicine_batches'),
            'offers' => $this->tableFor(\App\Models\MarketplaceOffer::class, 'marketplace_offers'),
        ];

        foreach (['pharmacies', 'branches', 'users', 'medicines', 'listings', 'batches', 'offers'] as $required) {
            if (! Schema::hasTable($tables[$required])) {
                throw new RuntimeException("Required table [{$tables[$required]}] does not exist. Run the project migrations first.");
            }
        }

        DB::transaction(function () use ($tables): void {
            $catalogue = $this->seedCatalogue($tables);
            $this->seedPharmaciesListingsStockAndOffers($tables, $catalogue);
        }, 5);

        $this->command?->newLine();
        $this->command?->info('Marketplace demo expansion installed successfully.');
        $this->command?->line('• 16 polished fictional catalogue medicines');
        $this->command?->line('• Professional primary images for every demo medicine');
        $this->command?->line('• Three pharmacy-specific price sets');
        $this->command?->line('• Two future stock batches per listing for FEFO demonstrations');
        $this->command?->line('• Active marketplace offers for Horizon, Umoja and Tanganyika');
    }

    /**
     * @param array<string, string> $tables
     * @return array<int, array<string, mixed>>
     */
    private function seedCatalogue(array $tables): array
    {
        $categories = [
            'Pain & Fever Relief' => 'Medicines intended for temporary management of pain, inflammation or fever.',
            'Allergy Care' => 'Products used for allergy relief and antihistamine care.',
            'Digestive & Rehydration' => 'Products supporting hydration, digestion and abdominal comfort.',
            'Vitamins & Supplements' => 'Nutritional support products for daily wellbeing.',
            'Cough & Cold' => 'Products used for temporary cough and cold symptom relief.',
            'Women & Blood Health' => 'Products supporting iron, folate and blood health needs.',
            'Anti-Infectives' => 'Prescription medicines used under professional supervision for infections.',
            'Antimalarials' => 'Medicines used under professional supervision in malaria management.',
            'Diabetes Care' => 'Products used in diabetes care and glucose management.',
        ];

        $categoryIds = [];
        if (Schema::hasTable($tables['categories'])) {
            foreach ($categories as $name => $description) {
                $categoryIds[$name] = $this->upsertId(
                    $tables['categories'],
                    ['name' => $name],
                    [
                        'slug' => Str::slug($name),
                        'description' => $description,
                        'is_active' => true,
                        'status' => 'active',
                    ],
                );
            }
        }

        $dosageForms = [
            'Tablet' => ['TAB', 'Solid oral medicine supplied in tablet form.'],
            'Capsule' => ['CAP', 'Solid oral medicine enclosed in a capsule.'],
            'Sachet' => ['SACH', 'Single-dose powdered product packaged in a sachet.'],
            'Syrup' => ['SYR', 'Liquid oral medicine supplied in a bottle.'],
            'Vial' => ['VIAL', 'Sterile medicine supplied in a sealed vial.'],
        ];

        $formIds = [];
        if (Schema::hasTable($tables['dosage_forms'])) {
            foreach ($dosageForms as $name => [$abbreviation, $description]) {
                $formIds[$name] = $this->upsertId(
                    $tables['dosage_forms'],
                    ['name' => $name],
                    [
                        'slug' => Str::slug($name),
                        'abbreviation' => $abbreviation,
                        'code' => $abbreviation,
                        'description' => $description,
                        'is_active' => true,
                        'status' => 'active',
                    ],
                );
            }
        }

        $manufacturers = [
            [
                'name' => 'BurMed Laboratories Demo',
                'country' => 'Burundi',
                'email' => 'contact@burmed-demo.test',
                'phone' => '+257 79 100 101',
                'address' => 'Industrial Demo Zone, Bujumbura, Burundi',
            ],
            [
                'name' => 'Great Lakes Pharma Demo',
                'country' => 'Rwanda',
                'email' => 'contact@greatlakes-demo.test',
                'phone' => '+250 700 100 202',
                'address' => 'Regional Pharmaceutical Demo Park, Kigali, Rwanda',
            ],
            [
                'name' => 'Tanganyika Health Products Demo',
                'country' => 'Tanzania',
                'email' => 'contact@tanganyika-health-demo.test',
                'phone' => '+255 700 100 303',
                'address' => 'Healthcare Demo Industrial Area, Dar es Salaam, Tanzania',
            ],
        ];

        $manufacturerIds = [];
        if (Schema::hasTable($tables['manufacturers'])) {
            foreach ($manufacturers as $manufacturer) {
                $manufacturerIds[] = $this->upsertId(
                    $tables['manufacturers'],
                    ['name' => $manufacturer['name']],
                    $manufacturer + [
                        'slug' => Str::slug($manufacturer['name']),
                        'is_active' => true,
                        'status' => 'active',
                    ],
                );
            }
        }

        $products = $this->products();
        $superAdminId = DB::table($tables['users'])
            ->whereNull('pharmacy_id')
            ->orderBy('id')
            ->value('id');

        $otcPrescriptionStatus = $this->existingPrescriptionStatus(
            $tables['medicines'],
            'otc',
            'otc',
        );
        $rxPrescriptionStatus = $this->existingPrescriptionStatus(
            $tables['medicines'],
            'prescription_required',
            'prescription_required',
        );

        foreach ($products as $index => &$product) {
            $manufacturerId = $manufacturerIds
                ? $manufacturerIds[$index % count($manufacturerIds)]
                : null;

            $medicineId = $this->upsertId(
                $tables['medicines'],
                ['brand_name' => $product['brand_name']],
                [
                    'slug' => Str::slug($product['brand_name'].'-'.$product['strength']),
                    'generic_name' => $product['generic_name'],
                    'medicine_category_id' => $categoryIds[$product['category']] ?? null,
                    'category_id' => $categoryIds[$product['category']] ?? null,
                    'dosage_form_id' => $formIds[$product['dosage_form']] ?? null,
                    'manufacturer_id' => $manufacturerId,
                    'strength' => $product['strength'],
                    'package_size' => $product['package_size'],
                    'barcode' => 'DEMO-BAR-'.strtoupper(substr(hash('sha256', $product['slug']), 0, 14)),
                    'regulatory_code' => 'DEMO-REG-'.strtoupper(substr(hash('sha256', 'REG-'.$product['slug']), 0, 12)),
                    'prescription_status' => in_array($product['online_sale_mode'], ['prescription_required', 'in_store_only'], true)
                        ? $rxPrescriptionStatus
                        : $otcPrescriptionStatus,
                    'online_sale_mode' => $product['online_sale_mode'],
                    'is_featured_marketplace' => $product['featured'],
                    'marketplace_featured' => $product['featured'],
                    'is_featured' => $product['featured'],
                    'marketplace_summary' => $product['marketplace_summary'],
                    'description' => $product['description'],
                    'indications' => $product['indications'],
                    'contraindications' => $product['contraindications'],
                    'possible_side_effects' => $product['possible_side_effects'],
                    'side_effects' => $product['possible_side_effects'],
                    'storage_instructions' => $product['storage_instructions'],
                    'approval_status' => 'approved',
                    'approved_by_user_id' => $superAdminId,
                    'approved_at' => now(),
                    'is_active' => true,
                    'status' => 'active',
                ],
            );

            $product['medicine_id'] = $medicineId;

            $this->seedMedicineImage($tables['images'], $medicineId, $product);
            $this->seedMedicineIngredient($tables, $medicineId, $product);
        }
        unset($product);

        return $products;
    }

    /**
     * @param array<string, string> $tables
     * @param array<int, array<string, mixed>> $products
     */
    private function seedPharmaciesListingsStockAndOffers(array $tables, array $products): void
    {
        $pharmacies = [
            [
                'name' => 'Pharmacie Horizon Santé',
                'legal_name' => 'Horizon Santé Burundi S.R.L.',
                'registration_number' => 'DEMO-RC-2026-001',
                'licence_number' => 'DEMO-PHARM-LIC-001',
                'tax_number' => 'DEMO-NIF-100001',
                'email' => 'horizon@demo-pharma.test',
                'phone' => '+257 79 000 101',
                'city' => 'Bujumbura',
                'province' => 'Bujumbura Mairie',
                'country' => 'Burundi',
                'address' => 'Avenue de la Santé, Rohero I, Demo Plot 101',
                'branch_name' => 'Horizon Santé — Rohero',
                'branch_code' => 'HZN-ROHERO',
                'branch_email' => 'rohero@horizon-demo.test',
                'branch_phone' => '+257 79 000 111',
                'owner_email' => 'owner@horizon-demo.test',
                'sku_prefix' => 'HZN',
                'price_factor' => 1.00,
                'delivery_fee' => 3000,
                'preparation_minutes' => 20,
            ],
            [
                'name' => 'Pharmacie Umoja Care',
                'legal_name' => 'Umoja Care Burundi S.R.L.',
                'registration_number' => 'DEMO-RC-2026-002',
                'licence_number' => 'DEMO-PHARM-LIC-002',
                'tax_number' => 'DEMO-NIF-100002',
                'email' => 'contact@umoja-demo.test',
                'phone' => '+257 79 000 202',
                'city' => 'Bujumbura',
                'province' => 'Bujumbura Mairie',
                'country' => 'Burundi',
                'address' => 'Boulevard de l’Unité, Kamenge, Demo Plot 202',
                'branch_name' => 'Umoja Care — Kamenge',
                'branch_code' => 'UMO-KAMENGE',
                'branch_email' => 'kamenge@umoja-demo.test',
                'branch_phone' => '+257 79 000 212',
                'owner_email' => 'owner@umoja-demo.test',
                'sku_prefix' => 'UMO',
                'price_factor' => 0.94,
                'delivery_fee' => 2500,
                'preparation_minutes' => 25,
            ],
            [
                'name' => 'Pharmacie Tanganyika Plus',
                'legal_name' => 'Tanganyika Plus Pharma S.R.L.',
                'registration_number' => 'DEMO-RC-2026-003',
                'licence_number' => 'DEMO-PHARM-LIC-003',
                'tax_number' => 'DEMO-NIF-100003',
                'email' => 'service@tanganyika-demo.test',
                'phone' => '+257 79 000 303',
                'city' => 'Bujumbura',
                'province' => 'Bujumbura Mairie',
                'country' => 'Burundi',
                'address' => 'Avenue du Lac, Kinindo, Demo Plot 303',
                'branch_name' => 'Tanganyika Plus — Kinindo',
                'branch_code' => 'TGP-KININDO',
                'branch_email' => 'kinindo@tanganyika-demo.test',
                'branch_phone' => '+257 79 000 313',
                'owner_email' => 'owner@tanganyika-demo.test',
                'sku_prefix' => 'TGP',
                'price_factor' => 1.08,
                'delivery_fee' => 4000,
                'preparation_minutes' => 35,
            ],
        ];

        foreach ($pharmacies as $pharmacyIndex => $pharmacyData) {
            $pharmacyId = $this->upsertId(
                $tables['pharmacies'],
                ['name' => $pharmacyData['name']],
                [
                    'legal_name' => $pharmacyData['legal_name'],
                    'registration_number' => $pharmacyData['registration_number'],
                    'pharmacy_licence_number' => $pharmacyData['licence_number'],
                    'licence_number' => $pharmacyData['licence_number'],
                    'tax_number' => $pharmacyData['tax_number'],
                    'email' => $pharmacyData['email'],
                    'phone' => $pharmacyData['phone'],
                    'city' => $pharmacyData['city'],
                    'province' => $pharmacyData['province'],
                    'country' => $pharmacyData['country'],
                    'address' => $pharmacyData['address'],
                    'status' => 'approved',
                    'approved_at' => now(),
                    'is_active' => true,
                    'notes' => 'Fictional approved partner pharmacy used for Home Pharma marketplace demonstration.',
                ],
            );

            $branchId = $this->upsertId(
                $tables['branches'],
                [
                    'pharmacy_id' => $pharmacyId,
                    'code' => $pharmacyData['branch_code'],
                ],
                [
                    'name' => $pharmacyData['branch_name'],
                    'email' => $pharmacyData['branch_email'],
                    'phone' => $pharmacyData['branch_phone'],
                    'city' => $pharmacyData['city'],
                    'province' => $pharmacyData['province'],
                    'country' => $pharmacyData['country'],
                    'address' => $pharmacyData['address'],
                    'is_main' => true,
                    'status' => 'active',
                    'is_active' => true,
                ],
            );

            $this->assignExistingOwnerToBranch(
                $tables['users'],
                $pharmacyData['owner_email'],
                $pharmacyId,
                $branchId,
            );

            foreach ($products as $productIndex => $product) {
                $onlinePrice = (int) (round(
                    ((float) $product['base_price'] * (float) $pharmacyData['price_factor']) / 100,
                ) * 100);
                $sellingPrice = $onlinePrice + 300;
                $sku = sprintf(
                    '%s-%s',
                    $pharmacyData['sku_prefix'],
                    strtoupper(substr(str_replace('-', '', $product['slug']), 0, 16)),
                );

                $listingId = $this->upsertId(
                    $tables['listings'],
                    [
                        'pharmacy_id' => $pharmacyId,
                        'medicine_id' => $product['medicine_id'],
                    ],
                    [
                        'sku' => $sku,
                        'selling_price' => $sellingPrice,
                        'online_price' => $onlinePrice,
                        'minimum_stock_level' => 10,
                        'reorder_quantity' => 50,
                        'expiry_warning_days' => 90,
                        'alerts_enabled' => true,
                        'is_available' => true,
                        'is_available_for_sale' => true,
                        'available_for_sale' => true,
                        'is_marketplace_visible' => true,
                        'marketplace_visible' => true,
                        'marketplace_enabled' => true,
                        'status' => 'active',
                        'description' => sprintf(
                            '%s supplied by %s from %s. Prices and fulfilment are specific to this pharmacy offer.',
                            $product['brand_name'],
                            $pharmacyData['name'],
                            $pharmacyData['branch_name'],
                        ),
                        'pharmacy_description' => sprintf(
                            '%s supplied by %s from %s.',
                            $product['brand_name'],
                            $pharmacyData['name'],
                            $pharmacyData['branch_name'],
                        ),
                    ],
                );

                $openingStock = 80 + (($productIndex * 17 + $pharmacyIndex * 23) % 90);
                $firstBatchQuantity = max(20, (int) floor($openingStock * 0.42));
                $secondBatchQuantity = $openingStock - $firstBatchQuantity;

                $this->seedBatch(
                    $tables['batches'],
                    $pharmacyId,
                    $branchId,
                    $listingId,
                    $pharmacyData['sku_prefix'].'-'.$product['slug'].'-A01',
                    $firstBatchQuantity,
                    (float) $product['unit_cost'],
                    now()->subMonths(2),
                    today()->addMonths(10)->addDays($productIndex),
                );

                $this->seedBatch(
                    $tables['batches'],
                    $pharmacyId,
                    $branchId,
                    $listingId,
                    $pharmacyData['sku_prefix'].'-'.$product['slug'].'-B02',
                    $secondBatchQuantity,
                    (float) $product['unit_cost'] * 1.04,
                    now()->subMonth(),
                    today()->addMonths(22)->addDays($productIndex),
                );

                $deliveryEnabled = ! in_array(
                    $product['online_sale_mode'],
                    ['prescription_required', 'in_store_only'],
                    true,
                );

                $maxOrderQuantity = match ($product['online_sale_mode']) {
                    'prescription_required', 'in_store_only' => 2,
                    'pharmacist_review' => 5,
                    default => 10,
                };

                $this->seedMarketplaceOffer(
                    $tables['offers'],
                    $pharmacyId,
                    $branchId,
                    $listingId,
                    $onlinePrice,
                    true,
                    $deliveryEnabled,
                    $deliveryEnabled ? (int) $pharmacyData['delivery_fee'] : 0,
                    $maxOrderQuantity,
                    (int) $pharmacyData['preparation_minutes'] + (($productIndex % 3) * 5),
                    $product,
                    $pharmacyData,
                );
            }
        }
    }

    /** @param array<string, mixed> $product */
    private function seedMedicineImage(string $table, int $medicineId, array $product): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $path = 'demo-medicines/'.$product['slug'].'.png';
        $absolutePath = storage_path('app/public/'.$path);

        if (! is_file($absolutePath)) {
            throw new RuntimeException("Demo image [{$absolutePath}] is missing. Extract the full ZIP before running the seeder.");
        }

        if ($this->hasColumn($table, 'is_primary')) {
            DB::table($table)
                ->where('medicine_id', $medicineId)
                ->update(['is_primary' => false]);
        }

        $this->upsertId(
            $table,
            [
                'medicine_id' => $medicineId,
                'path' => $path,
            ],
            [
                'disk' => 'public',
                'original_name' => $product['slug'].'.png',
                'mime_type' => 'image/png',
                'size_bytes' => filesize($absolutePath),
                'alt_text' => $product['brand_name'].' fictional demo package',
                'is_primary' => true,
                'sort_order' => 0,
                'order' => 0,
            ],
        );
    }

    /** @param array<string, string> $tables @param array<string, mixed> $product */
    private function seedMedicineIngredient(array $tables, int $medicineId, array $product): void
    {
        if (! Schema::hasTable($tables['molecules']) || ! Schema::hasTable($tables['ingredients'])) {
            return;
        }

        $moleculeId = $this->upsertId(
            $tables['molecules'],
            ['name' => $product['molecule']],
            [
                'slug' => Str::slug($product['molecule']),
                'description' => 'Fictional demonstration molecule record for Home Pharma marketplace validation.',
                'is_active' => true,
                'status' => 'active',
            ],
        );

        $this->upsertId(
            $tables['ingredients'],
            [
                'medicine_id' => $medicineId,
                'molecule_id' => $moleculeId,
            ],
            [
                'strength' => $product['ingredient_strength'],
                'is_primary' => true,
                'sort_order' => 0,
                'order' => 0,
            ],
        );
    }

    private function seedBatch(
        string $table,
        int $pharmacyId,
        int $branchId,
        int $listingId,
        string $batchNumber,
        int $quantity,
        float $unitCost,
        mixed $manufacturingDate,
        mixed $expiryDate,
    ): void {
        $this->upsertId(
            $table,
            [
                'pharmacy_id' => $pharmacyId,
                'batch_number' => strtoupper($batchNumber),
            ],
            [
                'pharmacy_branch_id' => $branchId,
                'branch_id' => $branchId,
                'pharmacy_medicine_id' => $listingId,
                'manufacturing_date' => $manufacturingDate,
                'expiry_date' => $expiryDate,
                'unit_cost' => round($unitCost, 2),
                'quantity_received' => $quantity,
                'quantity_available' => $quantity,
                'status' => 'active',
                'received_at' => now(),
                'notes' => 'Opening demo inventory created by MarketplaceDemoExpansionSeeder.',
            ],
        );
    }

    /** @param array<string, mixed> $product @param array<string, mixed> $pharmacyData */
    private function seedMarketplaceOffer(
        string $table,
        int $pharmacyId,
        int $branchId,
        int $listingId,
        int $onlinePrice,
        bool $pickupEnabled,
        bool $deliveryEnabled,
        int $deliveryFee,
        int $maxOrderQuantity,
        int $preparationMinutes,
        array $product,
        array $pharmacyData,
    ): void {
        $branchColumn = $this->hasColumn($table, 'pharmacy_branch_id')
            ? 'pharmacy_branch_id'
            : 'branch_id';

        $priceColumn = $this->hasColumn($table, 'price')
            ? 'price'
            : 'online_price';

        $identity = [
            'pharmacy_medicine_id' => $listingId,
            $branchColumn => $branchId,
        ];

        $this->upsertId(
            $table,
            $identity,
            [
                'pharmacy_id' => $pharmacyId,
                'pharmacy_branch_id' => $branchId,
                'branch_id' => $branchId,
                $priceColumn => $onlinePrice,
                'price' => $onlinePrice,
                'online_price' => $onlinePrice,
                'currency' => 'BIF',
                'pickup_enabled' => $pickupEnabled,
                'pickup_available' => $pickupEnabled,
                'is_pickup_available' => $pickupEnabled,
                'delivery_enabled' => $deliveryEnabled,
                'delivery_available' => $deliveryEnabled,
                'is_delivery_available' => $deliveryEnabled,
                'delivery_fee' => $deliveryFee,
                'max_order_quantity' => $maxOrderQuantity,
                'preparation_minutes' => $preparationMinutes,
                'preparation_time_minutes' => $preparationMinutes,
                'status' => 'active',
                'is_active' => true,
                'published_at' => now(),
                'description' => sprintf(
                    '%s offer from %s. %s',
                    $product['brand_name'],
                    $pharmacyData['name'],
                    $product['online_sale_mode'] === 'prescription_required'
                        ? 'A valid prescription and pharmacist approval are required.'
                        : ($product['online_sale_mode'] === 'in_store_only'
                            ? 'Visible for comparison but available in store only.'
                            : 'Available for safe marketplace reservation.'),
                ),
            ],
        );
    }

    private function assignExistingOwnerToBranch(
        string $usersTable,
        string $email,
        int $pharmacyId,
        int $branchId,
    ): void {
        $user = DB::table($usersTable)
            ->where('email', $email)
            ->first();

        if (! $user) {
            $this->command?->warn("Owner account [{$email}] does not exist; no password or account was created automatically.");
            return;
        }

        $updates = $this->filterColumns($usersTable, [
            'pharmacy_id' => $pharmacyId,
            'pharmacy_branch_id' => $branchId,
            'is_active' => true,
            'email_verified_at' => $user->email_verified_at ?? now(),
            'updated_at' => now(),
        ]);

        DB::table($usersTable)
            ->where('id', $user->id)
            ->update($updates);
    }

    private function existingPrescriptionStatus(string $medicinesTable, string $mode, string $fallback): string
    {
        if (! $this->hasColumn($medicinesTable, 'prescription_status')) {
            return $fallback;
        }

        if ($this->hasColumn($medicinesTable, 'online_sale_mode')) {
            $value = DB::table($medicinesTable)
                ->where('online_sale_mode', $mode)
                ->whereNotNull('prescription_status')
                ->value('prescription_status');

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return $fallback;
    }

    private function tableFor(string $modelClass, string $fallback): string
    {
        if (class_exists($modelClass)) {
            return (new $modelClass())->getTable();
        }

        return $fallback;
    }

    /** @param array<string, mixed> $identity @param array<string, mixed> $values */
    private function upsertId(string $table, array $identity, array $values): int
    {
        if (! Schema::hasTable($table)) {
            throw new RuntimeException("Table [{$table}] does not exist.");
        }

        $identity = $this->filterColumns($table, $identity);
        if ($identity === []) {
            throw new RuntimeException("No valid identity columns were available for table [{$table}].");
        }

        $query = DB::table($table);
        foreach ($identity as $column => $value) {
            $query->where($column, $value);
        }

        $existing = $query->first();
        $values = $this->filterColumns($table, $values);

        if ($existing) {
            if ($this->hasColumn($table, 'updated_at')) {
                $values['updated_at'] = now();
            }

            if ($values !== []) {
                $updateQuery = DB::table($table);
                foreach ($identity as $column => $value) {
                    $updateQuery->where($column, $value);
                }
                $updateQuery->update($values);
            }

            return $this->hasColumn($table, 'id')
                ? (int) $existing->id
                : 0;
        }

        $payload = array_merge($identity, $values);

        if ($this->hasColumn($table, 'uuid') && ! array_key_exists('uuid', $payload)) {
            $payload['uuid'] = (string) Str::uuid();
        }
        if ($this->hasColumn($table, 'created_at') && ! array_key_exists('created_at', $payload)) {
            $payload['created_at'] = now();
        }
        if ($this->hasColumn($table, 'updated_at') && ! array_key_exists('updated_at', $payload)) {
            $payload['updated_at'] = now();
        }

        if ($this->hasColumn($table, 'id')) {
            return (int) DB::table($table)->insertGetId($payload);
        }

        DB::table($table)->insert($payload);

        return 0;
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function filterColumns(string $table, array $data): array
    {
        return Arr::only($data, $this->columns($table));
    }

    /** @return array<int, string> */
    private function columns(string $table): array
    {
        return $this->columnCache[$table] ??= Schema::getColumnListing($table);
    }

    private function hasColumn(string $table, string $column): bool
    {
        return in_array($column, $this->columns($table), true);
    }

    /** @return array<int, array<string, mixed>> */
    private function products(): array
    {
        $commonContraindications = 'Follow the product leaflet and obtain advice from a qualified pharmacist or clinician when uncertain.';
        $commonSideEffects = 'Possible unwanted reactions vary by medicine. Stop use and seek professional advice when unusual symptoms occur.';
        $commonStorage = 'Store in the original package, protected from moisture and direct sunlight, and keep away from children.';

        return [
            $this->product('paracare-500', 'ParaCare', 'Paracetamol', '500 mg', 'Tablet', 'Box of 20 tablets', 'Pain & Fever Relief', 'otc', 'Paracetamol', '500 mg', 2500, 1750, true,
                'Everyday pain and fever relief available from approved partner pharmacies.',
                'Temporary relief of mild pain and fever, subject to package instructions and professional advice.', $commonContraindications, $commonSideEffects, $commonStorage),
            $this->product('cetirelief-10', 'CetiRelief', 'Cetirizine Hydrochloride', '10 mg', 'Tablet', 'Box of 10 tablets', 'Allergy Care', 'otc', 'Cetirizine Hydrochloride', '10 mg', 5000, 3500, true,
                'Allergy-care tablets with pharmacy-specific prices and fulfilment options.',
                'Allergy-care product to be used according to the package leaflet or professional advice.', $commonContraindications, $commonSideEffects, $commonStorage),
            $this->product('ors-balance', 'ORS Balance', 'Oral Rehydration Salts', 'Standard formula', 'Sachet', 'Pack of 10 sachets', 'Digestive & Rehydration', 'otc', 'Oral Rehydration Salts', '1 sachet', 1200, 650, true,
                'Convenient oral rehydration sachets available for pickup or delivery.',
                'Oral rehydration support used according to package preparation instructions and professional guidance.', $commonContraindications, $commonSideEffects, $commonStorage),
            $this->product('quinisafe-300', 'QuiniSafe', 'Quinine Sulfate', '300 mg', 'Tablet', 'Box of 20 tablets', 'Antimalarials', 'prescription_required', 'Quinine Sulfate', '300 mg', 12000, 8800, false,
                'Customers may compare prices, but fulfilment requires a valid prescription and pharmacist approval.',
                'Prescription-controlled medicine to be used only following qualified clinical assessment.', 'Must not be supplied through unrestricted online ordering.', $commonSideEffects, $commonStorage),
            $this->product('feverease-junior', 'FeverEase Junior', 'Paracetamol', '120 mg / 5 mL', 'Syrup', '100 mL bottle', 'Pain & Fever Relief', 'otc', 'Paracetamol', '120 mg / 5 mL', 6500, 4200, true,
                'Child-friendly fictional fever and pain relief syrup for marketplace demonstration.',
                'Temporary pain and fever relief used only according to age-appropriate package directions.', $commonContraindications, $commonSideEffects, $commonStorage),
            $this->product('allerclear-kids', 'AllerClear Kids', 'Cetirizine Hydrochloride', '5 mg / 5 mL', 'Syrup', '60 mL bottle', 'Allergy Care', 'otc', 'Cetirizine Hydrochloride', '5 mg / 5 mL', 7200, 4700, false,
                'Fictional allergy syrup with easy pharmacy offer comparison.',
                'Allergy symptom support used according to package directions and professional advice.', $commonContraindications, $commonSideEffects, $commonStorage),
            $this->product('zincplus-20', 'ZincPlus', 'Zinc Sulfate', '20 mg', 'Tablet', 'Box of 20 dispersible tablets', 'Vitamins & Supplements', 'otc', 'Zinc Sulfate', '20 mg', 4800, 3000, true,
                'Dispersible zinc supplement available from participating pharmacies.',
                'Nutritional supplementation used according to package directions or professional advice.', $commonContraindications, $commonSideEffects, $commonStorage),
            $this->product('vitac-boost-500', 'VitaC Boost', 'Ascorbic Acid', '500 mg', 'Tablet', 'Tube of 20 effervescent tablets', 'Vitamins & Supplements', 'otc', 'Ascorbic Acid', '500 mg', 8500, 5600, true,
                'Effervescent vitamin C demonstration product with multiple pharmacy prices.',
                'Vitamin supplementation used according to package directions.', $commonContraindications, $commonSideEffects, $commonStorage),
            $this->product('ibuease-400', 'IbuEase', 'Ibuprofen', '400 mg', 'Tablet', 'Box of 20 tablets', 'Pain & Fever Relief', 'pharmacist_review', 'Ibuprofen', '400 mg', 4500, 3000, false,
                'Pain-relief product visible for comparison; online orders require pharmacist review.',
                'Use only according to the package leaflet and qualified professional guidance.', 'May not be suitable for every client. Pharmacist review is required before online fulfilment.', $commonSideEffects, $commonStorage),
            $this->product('gastrocalm-10', 'GastroCalm', 'Hyoscine Butylbromide', '10 mg', 'Tablet', 'Box of 20 tablets', 'Digestive & Rehydration', 'pharmacist_review', 'Hyoscine Butylbromide', '10 mg', 7800, 5100, false,
                'Digestive comfort product requiring pharmacist review before online fulfilment.',
                'Temporary digestive discomfort support subject to professional review.', $commonContraindications, $commonSideEffects, $commonStorage),
            $this->product('coughease-dm', 'CoughEase DM', 'Dextromethorphan', '15 mg / 5 mL', 'Syrup', '100 mL bottle', 'Cough & Cold', 'pharmacist_review', 'Dextromethorphan', '15 mg / 5 mL', 9000, 6100, true,
                'Fictional cough syrup requiring pharmacist review for safe online ordering.',
                'Temporary cough symptom relief according to package instructions and pharmacist review.', $commonContraindications, $commonSideEffects, $commonStorage),
            $this->product('ironcare-plus', 'IronCare Plus', 'Ferrous Sulfate + Folic Acid', '200 mg + 0.4 mg', 'Tablet', 'Box of 30 tablets', 'Women & Blood Health', 'pharmacist_review', 'Ferrous Sulfate', '200 mg', 10500, 7100, false,
                'Iron and folate demonstration product with pharmacist-supported ordering.',
                'Supplementation used following professional advice and package directions.', $commonContraindications, $commonSideEffects, $commonStorage),
            $this->product('amoxiguard-500', 'AmoxiGuard', 'Amoxicillin Trihydrate', '500 mg', 'Capsule', 'Box of 21 capsules', 'Anti-Infectives', 'prescription_required', 'Amoxicillin Trihydrate', '500 mg', 9000, 6400, false,
                'Visible for comparison, but a valid prescription must be approved before fulfilment.',
                'Prescription medicine used only according to a qualified prescriber’s instructions.', 'Do not dispense without prescription validation and pharmacist review.', $commonSideEffects, $commonStorage),
            $this->product('metrosafe-400', 'MetroSafe', 'Metronidazole', '400 mg', 'Tablet', 'Box of 20 tablets', 'Anti-Infectives', 'prescription_required', 'Metronidazole', '400 mg', 7500, 5000, false,
                'Restricted anti-infective demonstration product requiring prescription approval.',
                'Prescription medicine used under qualified clinical supervision.', 'Do not dispense without prescription validation.', $commonSideEffects, $commonStorage),
            $this->product('artelum-20-120', 'ArteLum', 'Artemether + Lumefantrine', '20 mg / 120 mg', 'Tablet', 'Box of 24 tablets', 'Antimalarials', 'prescription_required', 'Artemether + Lumefantrine', '20 mg / 120 mg', 18000, 13200, false,
                'Restricted antimalarial demonstration product requiring clinical and pharmacy review.',
                'Prescription-controlled medicine used only following qualified clinical assessment.', 'Must not be supplied through unrestricted online ordering.', $commonSideEffects, $commonStorage),
            $this->product('insucare-r', 'InsuCare R', 'Human Insulin', '100 IU / mL', 'Vial', '10 mL vial', 'Diabetes Care', 'in_store_only', 'Human Insulin', '100 IU / mL', 22500, 17500, false,
                'Visible for price and availability comparison, but available through in-store handling only.',
                'Diabetes medicine used only under qualified clinical supervision.', 'Requires controlled storage, professional instructions and in-store fulfilment.', $commonSideEffects, 'Keep refrigerated according to authorised product instructions. Do not freeze.'),
        ];
    }

    /** @return array<string, mixed> */
    private function product(
        string $slug,
        string $brandName,
        string $genericName,
        string $strength,
        string $dosageForm,
        string $packageSize,
        string $category,
        string $onlineSaleMode,
        string $molecule,
        string $ingredientStrength,
        int $basePrice,
        int $unitCost,
        bool $featured,
        string $marketplaceSummary,
        string $indications,
        string $contraindications,
        string $possibleSideEffects,
        string $storageInstructions,
    ): array {
        return [
            'slug' => $slug,
            'brand_name' => $brandName,
            'generic_name' => $genericName,
            'strength' => $strength,
            'dosage_form' => $dosageForm,
            'package_size' => $packageSize,
            'category' => $category,
            'online_sale_mode' => $onlineSaleMode,
            'molecule' => $molecule,
            'ingredient_strength' => $ingredientStrength,
            'base_price' => $basePrice,
            'unit_cost' => $unitCost,
            'featured' => $featured,
            'marketplace_summary' => $marketplaceSummary,
            'description' => "{$brandName} is a fictional Home Pharma demonstration product created for marketplace presentation, price comparison and workflow validation.",
            'indications' => $indications,
            'contraindications' => $contraindications,
            'possible_side_effects' => $possibleSideEffects,
            'storage_instructions' => $storageInstructions,
        ];
    }
}
