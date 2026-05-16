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
        Schema::create('topology_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topology_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_topology_device_id')->constrained('topology_devices')->cascadeOnDelete();
            $table->foreignId('to_topology_device_id')->constrained('topology_devices')->cascadeOnDelete();
            $table->string('from_interface_name')->nullable();
            $table->string('to_interface_name')->nullable();
            $table->string('link_type')->default('routed');
            $table->unsignedSmallInteger('vlan_id')->nullable();
            $table->string('allowed_vlans')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('topology_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('topology_links');
    }
};
