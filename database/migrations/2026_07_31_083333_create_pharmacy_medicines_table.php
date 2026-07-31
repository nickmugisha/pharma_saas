<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pharmacy_medicines', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('pharmacy_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('medicine_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('internal_sku', 100)->nullable();

            $table->decimal('selling_price', 15, 2);
            $table->decimal('online_price', 15, 2)->nullable();

            $table->string('currency', 3)
                ->default('BIF');

            $table->text('pharmacy_description')->nullable();

            $table->boolean('is_available')
                ->default(true)
                ->index();

            $table->boolean('is_visible_online')
                ->default(false)
                ->index();

            $table->string('status', 30)
                ->default('active')
                ->index();

            $table->timestamps();
            $table->softDeletes();

            $table->unique([
                'pharmacy_id',
                'medicine_id',
            ]);

            $table->unique([
                'pharmacy_id',
                'internal_sku',
            ]);

            $table->index([
                'pharmacy_id',
                'status',
                'is_visible_online',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pharmacy_medicines');
    }
};