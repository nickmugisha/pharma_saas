<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pharmacy_medicines', function (Blueprint $table): void {
            $table->decimal('minimum_stock_level', 15, 3)
                ->default(0)
                ->after('selling_price');

            $table->decimal('reorder_quantity', 15, 3)
                ->default(0)
                ->after('minimum_stock_level');

            $table->unsignedSmallInteger('expiry_warning_days')
                ->default(90)
                ->after('reorder_quantity');

            $table->boolean('alerts_enabled')
                ->default(true)
                ->after('expiry_warning_days');

            $table->index(
                ['pharmacy_id', 'alerts_enabled'],
                'pm_pharm_alerts_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('pharmacy_medicines', function (Blueprint $table): void {
            $table->dropIndex('pm_pharm_alerts_idx');

            $table->dropColumn([
                'minimum_stock_level',
                'reorder_quantity',
                'expiry_warning_days',
                'alerts_enabled',
            ]);
        });
    }
};