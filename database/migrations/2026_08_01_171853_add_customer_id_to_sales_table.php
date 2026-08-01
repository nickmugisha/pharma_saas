<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->foreignId('customer_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->index(
                ['pharmacy_id', 'customer_id', 'sold_at'],
                'sales_pharm_customer_date_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->dropIndex(
                'sales_pharm_customer_date_idx',
            );

            $table->dropConstrainedForeignId(
                'customer_id',
            );
        });
    }
};