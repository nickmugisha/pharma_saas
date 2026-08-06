<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medicines', function (Blueprint $table): void {
            $table->string('online_sale_mode', 40)
                ->default('otc')
                ->after('prescription_status')
                ->index();

            $table->text('marketplace_summary')
                ->nullable()
                ->after('description');

            $table->boolean('is_marketplace_featured')
                ->default(false)
                ->after('is_active')
                ->index();
        });

        DB::table('medicines')
            ->where('prescription_status', 'prescription')
            ->update(['online_sale_mode' => 'prescription_required']);

        DB::table('medicines')
            ->where('prescription_status', 'controlled')
            ->update(['online_sale_mode' => 'in_store_only']);
    }

    public function down(): void
    {
        Schema::table('medicines', function (Blueprint $table): void {
            $table->dropColumn([
                'online_sale_mode',
                'marketplace_summary',
                'is_marketplace_featured',
            ]);
        });
    }
};
