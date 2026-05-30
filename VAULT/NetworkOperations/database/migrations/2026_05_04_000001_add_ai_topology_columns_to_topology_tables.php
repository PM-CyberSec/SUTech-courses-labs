<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('topologies', function (Blueprint $table): void {
            $table->string('scenario_type')->nullable()->after('slug');
            $table->foreignId('created_by')->nullable()->after('scenario_type')->constrained('users')->nullOnDelete();
            $table->string('status')->default('draft')->after('created_by');
        });

        Schema::table('topology_devices', function (Blueprint $table): void {
            $table->string('name')->nullable()->after('hostname');
            $table->string('type')->nullable()->after('name');
            $table->string('model')->nullable()->after('type');
            $table->string('role')->nullable()->after('model');
            $table->integer('x_position')->nullable()->after('role');
            $table->integer('y_position')->nullable()->after('x_position');
        });

        Schema::table('topology_links', function (Blueprint $table): void {
            $table->foreignId('source_device_id')->nullable()->after('topology_id')->constrained('topology_devices')->cascadeOnDelete();
            $table->string('source_interface')->nullable()->after('source_device_id');
            $table->foreignId('target_device_id')->nullable()->after('source_interface')->constrained('topology_devices')->cascadeOnDelete();
            $table->string('target_interface')->nullable()->after('target_device_id');
            $table->string('cable_type')->nullable()->after('target_interface');
            $table->string('status')->default('planned')->after('cable_type');
        });
    }

    public function down(): void
    {
        Schema::table('topology_links', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('source_device_id');
            $table->dropColumn(['source_interface', 'target_interface', 'cable_type', 'status']);
            $table->dropConstrainedForeignId('target_device_id');
        });

        Schema::table('topology_devices', function (Blueprint $table): void {
            $table->dropColumn(['name', 'type', 'model', 'role', 'x_position', 'y_position']);
        });

        Schema::table('topologies', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['scenario_type', 'status']);
        });
    }
};