<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pharmacy_branches', function (Blueprint $table): void {
            $table->uuid('uuid')->unique();

            $table->foreignId('pharmacy_id')
                ->constrained('pharmacies')
                ->cascadeOnDelete();

            $table->string('name');
            $table->string('code', 50);

            $table->boolean('is_main')
                ->default(false)
                ->index();

            $table->string('status', 30)
                ->default('active')
                ->index();

            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();

            $table->text('address')->nullable();
            $table->string('city')->nullable()->index();
            $table->string('province')->nullable()->index();

            $table->softDeletes();

            $table->unique(['pharmacy_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::table('pharmacy_branches', function (Blueprint $table): void {
            $table->dropUnique(['pharmacy_id', 'code']);
            $table->dropForeign(['pharmacy_id']);

            $table->dropColumn([
                'uuid',
                'pharmacy_id',
                'name',
                'code',
                'is_main',
                'status',
                'email',
                'phone',
                'address',
                'city',
                'province',
                'deleted_at',
            ]);
        });
    }
};