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
    Schema::table('users', function (Blueprint $table) {
        $table->boolean('is_active')->default(true)->index()->after('password');
        $table->timestamp('blocked_at')->nullable()->after('is_active');
        $table->string('blocked_reason')->nullable()->after('blocked_at');
        $table->timestamp('last_login_at')->nullable()->after('blocked_reason');
        $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
    });
}

public function down(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->dropColumn([
            'is_active',
            'blocked_at',
            'blocked_reason',
            'last_login_at',
            'last_login_ip',
        ]);
    });
}
};
