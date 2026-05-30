<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table): void {
            $table->foreign('inventory_id')
                ->references('id')
                ->on('inventories')
                ->nullOnDelete();
        });

        Schema::table('deployments', function (Blueprint $table): void {
            $table->foreign('inventory_id')
                ->references('id')
                ->on('inventories')
                ->nullOnDelete();
        });

        Schema::table('deployment_logs', function (Blueprint $table): void {
            $table->foreign('deployment_id')
                ->references('id')
                ->on('deployments')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table): void {
            $table->dropForeign(['inventory_id']);
        });

        Schema::table('deployments', function (Blueprint $table): void {
            $table->dropForeign(['inventory_id']);
        });

        Schema::table('deployment_logs', function (Blueprint $table): void {
            $table->dropForeign(['deployment_id']);
        });
    }
};
