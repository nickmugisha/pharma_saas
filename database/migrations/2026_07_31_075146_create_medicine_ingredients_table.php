<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medicine_ingredients', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('medicine_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('molecule_id')
                ->constrained()
                ->restrictOnDelete();

            $table->string('strength')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();

            $table->unique([
                'medicine_id',
                'molecule_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medicine_ingredients');
    }
};