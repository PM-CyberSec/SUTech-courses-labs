<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role', 20)->default('viewer')->after('password');
            $table->boolean('is_active')->default(true)->after('role');
            $table->timestamp('approved_at')->nullable()->after('is_active');
        });

        DB::table('users')
            ->whereNull('approved_at')
            ->update([
                'approved_at' => now(),
                'is_active' => true,
                'role' => DB::raw("COALESCE(NULLIF(role, ''), 'viewer')"),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['role', 'is_active', 'approved_at']);
        });
    }
};

