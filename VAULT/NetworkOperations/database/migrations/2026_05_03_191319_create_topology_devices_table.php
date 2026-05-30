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
        Schema::create('topology_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topology_id')->constrained()->cascadeOnDelete();
            $table->string('hostname');
            $table->string('device_type');
            $table->string('enable_secret')->nullable();
            $table->string('console_password')->nullable();
            $table->string('vty_password')->nullable();
            $table->boolean('service_password_encryption')->default(true);
            $table->string('routing_protocol')->nullable();
            $table->string('default_gateway')->nullable();
            $table->json('vlans')->nullable();
            $table->json('static_routes')->nullable();
            $table->json('dhcp_pools')->nullable();
            $table->json('nat_rules')->nullable();
            $table->json('acl_rules')->nullable();
            $table->json('ssh_settings')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['topology_id', 'hostname']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('topology_devices');
    }
};
