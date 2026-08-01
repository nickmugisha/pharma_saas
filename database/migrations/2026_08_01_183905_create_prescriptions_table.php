<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prescriptions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('pharmacy_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('pharmacy_branch_id')
                ->constrained('pharmacy_branches')
                ->restrictOnDelete();

            $table->foreignId('customer_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('reviewed_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('prescription_number', 100);

            $table->string('status', 40)
                ->default('draft')
                ->index();

            $table->string('source', 30)
                ->default('uploaded');

            $table->string('prescriber_name', 191);
            $table->string('prescriber_phone', 50)->nullable();
            $table->string('prescriber_facility', 191)->nullable();

            $table->string(
                'prescriber_registration_number',
                100,
            )->nullable();

            $table->date('issued_at')->nullable();
            $table->date('valid_until')->nullable();

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('dispensed_at')->nullable();

            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(
                ['pharmacy_id', 'prescription_number'],
                'prescriptions_pharm_number_unique',
            );

            $table->index(
                ['pharmacy_id', 'status', 'issued_at'],
                'prescriptions_pharm_status_date_idx',
            );

            $table->index(
                ['pharmacy_id', 'customer_id', 'status'],
                'prescriptions_customer_status_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescriptions');
    }
};