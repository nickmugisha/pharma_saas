<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_addresses', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label', 80)->default('Home');
            $table->string('recipient_name', 191);
            $table->string('phone', 50);
            $table->string('address_line_1');
            $table->string('address_line_2')->nullable();
            $table->string('city', 100)->default('Bujumbura');
            $table->string('province', 100)->nullable();
            $table->string('country', 100)->default('Burundi');
            $table->text('delivery_instructions')->nullable();
            $table->boolean('is_default')->default(false)->index();
            $table->timestamps();

            $table->index(['user_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_addresses');
    }
};
