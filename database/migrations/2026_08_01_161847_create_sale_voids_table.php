<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_voids', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('pharmacy_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('sale_id')
                ->unique()
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('voided_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('void_number', 100);
            $table->text('reason');

            $table->decimal(
                'restored_quantity',
                15,
                3,
            )->default(0);

            $table->decimal(
                'reversed_payment_amount',
                15,
                2,
            )->default(0);

            $table->timestamp('voided_at')->index();
            $table->timestamps();

            $table->unique(
                ['pharmacy_id', 'void_number'],
                'sale_void_pharmacy_number_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_voids');
    }
};