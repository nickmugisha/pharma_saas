<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'phone')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('phone', 50)
                    ->nullable()
                    ->after('email');
            });
        }

        if (! Schema::hasColumn('users', 'job_title')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('job_title', 120)
                    ->nullable()
                    ->after('phone');
            });
        }

        if (! Schema::hasColumn('users', 'hired_at')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->date('hired_at')
                    ->nullable()
                    ->after('job_title');
            });
        }

        if (! Schema::hasColumn('users', 'invited_by_user_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->foreignId('invited_by_user_id')
                    ->nullable()
                    ->after('pharmacy_branch_id')
                    ->constrained('users')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('users', 'staff_updated_by_user_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->foreignId('staff_updated_by_user_id')
                    ->nullable()
                    ->after('invited_by_user_id')
                    ->constrained('users')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'staff_updated_by_user_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('staff_updated_by_user_id');
            });
        }

        if (Schema::hasColumn('users', 'invited_by_user_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('invited_by_user_id');
            });
        }

        foreach (['hired_at', 'job_title', 'phone'] as $column) {
            if (Schema::hasColumn('users', $column)) {
                Schema::table('users', function (Blueprint $table) use ($column): void {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
