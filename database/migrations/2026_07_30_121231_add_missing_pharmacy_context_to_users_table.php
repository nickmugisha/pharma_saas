<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'pharmacy_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->foreignId('pharmacy_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('pharmacies')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('users', 'pharmacy_branch_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->foreignId('pharmacy_branch_id')
                    ->nullable()
                    ->after('pharmacy_id')
                    ->constrained('pharmacy_branches')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'pharmacy_branch_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('pharmacy_branch_id');
            });
        }

        if (Schema::hasColumn('users', 'pharmacy_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('pharmacy_id');
            });
        }
    }
};