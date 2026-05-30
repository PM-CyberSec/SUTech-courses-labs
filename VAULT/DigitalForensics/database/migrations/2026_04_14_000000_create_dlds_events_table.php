<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Schema aligned with detection-engine/correlator.py SQLite insert and JSON POST body.
     */
    public function up()
    {
        Schema::create('dlds_events', function (Blueprint $table) {
            $table->id();
            $table->string('timestamp')->nullable();
            $table->string('type')->nullable();
            $table->integer('pid')->default(0);
            $table->string('process_name')->nullable();
            $table->string('file')->nullable();
            $table->string('src_ip')->nullable();
            $table->integer('src_port')->default(0);
            $table->string('dst_ip')->nullable();
            $table->integer('dst_port')->default(0);
            $table->bigInteger('bytes_sent')->default(0);
            $table->string('alert_type')->nullable();
            $table->string('severity')->default('LOW');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dlds_events');
    }
};
