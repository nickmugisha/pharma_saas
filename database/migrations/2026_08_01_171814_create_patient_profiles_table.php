<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'patient_profiles',
            function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();

                $table->foreignId('customer_id')
                    ->unique()
                    ->constrained()
                    ->restrictOnDelete();

                $table->foreignId('created_by_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->date('date_of_birth')->nullable();

                $table->string('sex', 30)->nullable();

                $table->string(
                    'emergency_contact_name',
                    191,
                )->nullable();

                $table->string(
                    'emergency_contact_phone',
                    50,
                )->nullable();

                $table->string(
                    'emergency_contact_relation',
                    100,
                )->nullable();

                $table->timestamps();
            },
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_profiles');
    }
};