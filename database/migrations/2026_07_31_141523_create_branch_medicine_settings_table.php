<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_medicine_settings', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('pharmacy_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('pharmacy_branch_id')
                ->constrained('pharmacy_branches')
                ->cascadeOnDelete();

            $table->foreignId('pharmacy_medicine_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->decimal('minimum_stock_level', 15, 3)
                ->default(0);

            $table->decimal('reorder_quantity', 15, 3)
                ->default(0);

            $table->unsignedSmallInteger('expiry_warning_days')
                ->default(90);

            $table->boolean('alerts_enabled')
                ->default(true);

            $table->timestamps();

            $table->unique(
                ['pharmacy_branch_id', 'pharmacy_medicine_id'],
                'bms_branch_medicine_unique',
            );

            $table->index(
                ['pharmacy_id', 'alerts_enabled'],
                'bms_pharm_alerts_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_medicine_settings');
    }
};