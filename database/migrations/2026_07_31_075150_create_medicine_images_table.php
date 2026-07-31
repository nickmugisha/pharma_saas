<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medicine_images', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('medicine_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('disk', 50)->default('public');
            $table->string('path');
            $table->string('alt_text')->nullable();

            $table->boolean('is_primary')
                ->default(false)
                ->index();

            $table->unsignedSmallInteger('sort_order')
                ->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medicine_images');
    }
};