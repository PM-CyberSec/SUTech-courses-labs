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
        Schema::create('generated_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topology_id')->constrained()->cascadeOnDelete();
            $table->foreignId('topology_device_id')->constrained('topology_devices')->cascadeOnDelete();
            $table->longText('config_text');
            $table->string('config_hash', 64);
            $table->json('validation_errors')->nullable();
            $table->timestamp('generated_at')->useCurrent();
            $table->timestamps();

            $table->unique(['topology_id', 'topology_device_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('generated_configs');
    }
};
