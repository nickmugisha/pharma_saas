<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medicines', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('slug')->unique();

            $table->string('brand_name')->index();
            $table->string('generic_name')->nullable()->index();

            $table->foreignId('medicine_category_id')
                ->nullable()
                ->constrained('medicine_categories')
                ->nullOnDelete();

            $table->foreignId('dosage_form_id')
                ->nullable()
                ->constrained('dosage_forms')
                ->nullOnDelete();

            $table->foreignId('manufacturer_id')
                ->nullable()
                ->constrained('manufacturers')
                ->nullOnDelete();

            $table->string('strength')->nullable();
            $table->string('package_size')->nullable();
            $table->string('barcode', 100)->nullable()->unique();
            $table->string('regulatory_code', 100)->nullable()->index();

            $table->text('description')->nullable();
            $table->text('indications')->nullable();
            $table->text('contraindications')->nullable();
            $table->text('side_effects')->nullable();
            $table->text('storage_instructions')->nullable();

            $table->string('prescription_status', 30)
                ->default('otc')
                ->index();

            $table->string('approval_status', 30)
                ->default('draft')
                ->index();

            $table->foreignId('submitted_by_pharmacy_id')
                ->nullable()
                ->constrained('pharmacies')
                ->nullOnDelete();

            $table->foreignId('submitted_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('reviewed_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();

            $table->text('review_notes')->nullable();

            $table->boolean('is_active')
                ->default(true)
                ->index();

            $table->timestamps();
            $table->softDeletes();

            $table->index([
                'approval_status',
                'prescription_status',
                'is_active',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medicines');
    }
};