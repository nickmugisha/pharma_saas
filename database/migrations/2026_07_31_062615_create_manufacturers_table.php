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
    Schema::create('manufacturers', function (Blueprint $table): void {
        $table->id();
        $table->uuid('uuid')->unique();
        $table->string('name')->unique();
        $table->string('country')->nullable()->index();
        $table->string('email')->nullable();
        $table->string('phone', 30)->nullable();
        $table->string('website')->nullable();
        $table->text('address')->nullable();
        $table->boolean('is_active')->default(true)->index();
        $table->timestamps();
        $table->softDeletes();
    });
}

public function down(): void
{
    Schema::dropIfExists('manufacturers');
}
};
