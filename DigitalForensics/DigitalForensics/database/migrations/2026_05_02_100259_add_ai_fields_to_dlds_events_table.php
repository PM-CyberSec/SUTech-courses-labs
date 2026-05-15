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
        Schema::table('dlds_events', function (Blueprint $table) {
            $table->string('ai_label')->nullable();
            $table->float('confidence')->nullable();
            $table->float('anomaly_score')->nullable();
            $table->json('ai_evidence')->nullable();
            $table->text('correlation_summary')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dlds_events', function (Blueprint $table) {
            $table->dropColumn([
                'ai_label',
                'confidence',
                'anomaly_score',
                'ai_evidence',
                'correlation_summary',
            ]);
        });
    }
};
