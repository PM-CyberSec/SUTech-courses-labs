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
            $table->text('ai_reason')->nullable();
            $table->string('model_version')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dlds_events', function (Blueprint $table) {
            $table->dropColumn(['ai_reason', 'model_version']);
        });
    }
};
