<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('config_templates', function (Blueprint $table): void {
            $table->string('template_group')->default('switching')->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('config_templates', function (Blueprint $table): void {
            $table->dropColumn('template_group');
        });
    }
};
