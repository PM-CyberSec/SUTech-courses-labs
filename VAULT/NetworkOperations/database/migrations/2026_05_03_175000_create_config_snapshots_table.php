<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('config_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->foreignId('deployment_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('snapshot_type', ['generated', 'successful', 'rollback'])->default('generated');
            $table->longText('config_body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('config_snapshots');
    }
};
