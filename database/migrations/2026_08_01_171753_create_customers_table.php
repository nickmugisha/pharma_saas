<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('pharmacy_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('registered_branch_id')
                ->nullable()
                ->constrained('pharmacy_branches')
                ->nullOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('customer_number', 100);
            $table->string('name', 191);

            $table->string('phone', 50)->nullable();
            $table->string('email', 191)->nullable();

            $table->string('address', 255)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('country', 100)
                ->default('Burundi');

            $table->string('preferred_language', 10)
                ->default('fr');

            $table->string('status', 30)
                ->default('active')
                ->index();

            $table->timestamp('registered_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(
                ['pharmacy_id', 'customer_number'],
                'customers_pharm_number_unique',
            );

            $table->unique(
                ['pharmacy_id', 'user_id'],
                'customers_pharm_user_unique',
            );

            $table->index(
                ['pharmacy_id', 'status', 'name'],
                'customers_pharm_status_name_idx',
            );

            $table->index(
                ['pharmacy_id', 'phone'],
                'customers_pharm_phone_idx',
            );

            $table->index(
                ['pharmacy_id', 'email'],
                'customers_pharm_email_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};