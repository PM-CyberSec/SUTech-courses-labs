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
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_id')->nullable();
            $table->string('hostname');
            $table->ipAddress('mgmt_ip')->unique();
            $table->string('ansible_host')->nullable();
            $table->unsignedSmallInteger('ssh_port')->default(22);
            $table->string('platform')->default('ios');
            $table->string('vendor')->nullable();
            $table->string('auth_username');
            $table->string('auth_password')->nullable();
            $table->string('become_password')->nullable();
            $table->string('connection')->default('network_cli');
            $table->enum('status', ['provisioning', 'active', 'maintenance', 'disabled'])->default('active');
            $table->json('metadata')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
