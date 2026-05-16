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
        Schema::create('deployments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_id')->nullable();
            $table->foreignId('config_template_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('playbook_name');
            $table->enum('status', ['pending', 'running', 'success', 'failed', 'rolled_back'])->default('pending');
            $table->enum('precheck_status', ['pending', 'passed', 'failed', 'skipped'])->default('pending');
            $table->enum('postcheck_status', ['pending', 'passed', 'failed', 'skipped'])->default('pending');
            $table->boolean('is_idempotent')->nullable();
            $table->json('variables')->nullable();
            $table->json('validation_results')->nullable();
            $table->boolean('simulation_mode')->default(false);
            $table->longText('generated_config')->nullable();
            $table->string('rendered_config_path')->nullable();
            $table->text('ansible_command')->nullable();
            $table->longText('output')->nullable();
            $table->longText('errors')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deployments');
    }
};
