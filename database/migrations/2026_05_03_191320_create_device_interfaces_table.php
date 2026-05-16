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
        Schema::create('device_interfaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topology_device_id')->constrained('topology_devices')->cascadeOnDelete();
            $table->string('name');
            $table->string('mode')->default('routed');
            $table->string('ip_address')->nullable();
            $table->string('subnet_mask')->nullable();
            $table->unsignedSmallInteger('vlan_id')->nullable();
            $table->unsignedSmallInteger('native_vlan')->nullable();
            $table->string('allowed_vlans')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_shutdown')->default(false);
            $table->timestamps();

            $table->unique(['topology_device_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_interfaces');
    }
};
