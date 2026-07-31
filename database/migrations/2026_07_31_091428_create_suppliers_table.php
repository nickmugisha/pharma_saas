<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('pharmacy_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('name');
            $table->string('contact_person')->nullable();

            $table->string('email')->nullable();
            $table->string('phone', 30);
            $table->string('alternate_phone', 30)->nullable();

            $table->string('registration_number')->nullable();
            $table->string('tax_number')->nullable();

            $table->text('address')->nullable();
            $table->string('city')->nullable()->index();
            $table->string('province')->nullable();
            $table->string('country')->default('Burundi');

            $table->unsignedSmallInteger('payment_terms_days')
                ->default(0);

            $table->decimal('credit_limit', 15, 2)
                ->nullable();

            $table->string('currency', 3)
                ->default('BIF');

            $table->string('status', 30)
                ->default('active')
                ->index();

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique([
                'pharmacy_id',
                'name',
            ]);

            $table->index([
                'pharmacy_id',
                'status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};