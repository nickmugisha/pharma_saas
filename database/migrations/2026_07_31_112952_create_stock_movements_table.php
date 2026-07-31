<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('pharmacy_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('pharmacy_branch_id')
                ->constrained('pharmacy_branches')
                ->restrictOnDelete();

            $table->foreignId('pharmacy_medicine_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('medicine_batch_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('movement_type', 40)->index();
            $table->string('direction', 10)->index();

            $table->decimal('quantity', 15, 3);
            $table->decimal('unit_cost', 15, 2)->nullable();
            $table->decimal('balance_after', 15, 3);

            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();

            $table->timestamp('occurred_at')->index();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(
                ['reference_type', 'reference_id'],
                'sm_reference_idx',
            );

            $table->index(
                [
                    'pharmacy_id',
                    'pharmacy_branch_id',
                    'movement_type',
                    'occurred_at',
                ],
                'sm_pharm_branch_type_date_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};