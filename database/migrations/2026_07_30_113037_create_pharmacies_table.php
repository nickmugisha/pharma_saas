<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
{
    Schema::create('pharmacies', function (Blueprint $table) {
        $table->id();
        $table->uuid('uuid')->unique();

        $table->string('name');
        $table->string('legal_name')->nullable();

        $table->string('registration_number')->nullable()->unique();
        $table->string('license_number')->nullable()->unique();
        $table->string('tax_number')->nullable()->unique();

        $table->string('email')->nullable();
        $table->string('phone', 30);
        $table->string('alternate_phone', 30)->nullable();

        $table->text('address')->nullable();
        $table->string('city')->nullable()->index();
        $table->string('province')->nullable()->index();
        $table->string('country')->default('Burundi');

        $table->string('status', 30)
            ->default('pending')
            ->index();

        $table->timestamp('approved_at')->nullable();
        $table->timestamp('suspended_at')->nullable();
        $table->string('suspension_reason')->nullable();

        $table->text('notes')->nullable();

        $table->timestamps();
        $table->softDeletes();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pharmacies');
    }
};
