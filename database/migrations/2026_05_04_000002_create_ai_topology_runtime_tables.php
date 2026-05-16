<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('topology_interfaces', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('topology_device_id')->constrained('topology_devices')->cascadeOnDelete();
            $table->string('name');
            $table->string('type')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('subnet_mask')->nullable();
            $table->unsignedSmallInteger('vlan_id')->nullable();
            $table->string('mode')->nullable();
            $table->string('status')->default('planned');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['topology_device_id', 'name']);
        });

        Schema::create('topology_configs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('topology_id')->constrained('topologies')->cascadeOnDelete();
            $table->foreignId('topology_device_id')->constrained('topology_devices')->cascadeOnDelete();
            $table->string('config_type');
            $table->longText('generated_cli');
            $table->string('validation_status')->default('pending');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['topology_id', 'topology_device_id']);
        });

        Schema::create('topology_validation_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('topology_id')->constrained('topologies')->cascadeOnDelete();
            $table->string('severity');
            $table->string('category');
            $table->text('message');
            $table->foreignId('device_id')->nullable()->constrained('topology_devices')->nullOnDelete();
            $table->foreignId('link_id')->nullable()->constrained('topology_links')->nullOnDelete();
            $table->text('suggested_fix')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('topology_validation_results');
        Schema::dropIfExists('topology_configs');
        Schema::dropIfExists('topology_interfaces');
    }
};