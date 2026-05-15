<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('severity_levels', function (Blueprint $table): void {
            $table->tinyIncrements('id');
            $table->string('name', 50)->unique();
            $table->timestamps();
        });

        Schema::create('event_types', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100)->unique();
            $table->timestamps();
        });

        Schema::create('alert_types', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150)->unique();
            $table->timestamps();
        });

        Schema::create('process_catalog', function (Blueprint $table): void {
            $table->id();
            $table->string('process_name')->unique();
            $table->timestamps();
        });

        Schema::create('dlds_events', function (Blueprint $table): void {
            $table->id();
            $table->dateTime('event_time')->nullable()->index();
            $table->foreignId('event_type_id')
                ->nullable()
                ->constrained('event_types')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->integer('pid')->default(0);
            $table->foreignId('process_id')
                ->nullable()
                ->constrained('process_catalog')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->string('file_path')->nullable();
            $table->string('src_ip', 45)->nullable()->index();
            $table->integer('src_port')->default(0);
            $table->string('dst_ip', 45)->nullable()->index();
            $table->integer('dst_port')->default(0);
            $table->bigInteger('bytes_sent')->default(0);
            $table->foreignId('alert_type_id')
                ->nullable()
                ->constrained('alert_types')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->unsignedTinyInteger('severity_id');
            $table->text('description')->nullable();
            $table->string('event_hash', 64)->nullable()->unique();
            $table->timestamps();

            $table->foreign('severity_id')
                ->references('id')
                ->on('severity_levels')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });

        $now = now();

        DB::table('severity_levels')->insert([
            ['id' => 1, 'name' => 'LOW', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => 'MEDIUM', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'name' => 'HIGH', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 4, 'name' => 'CRITICAL', 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('event_types')->insert([
            ['name' => 'network', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'file', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'process', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'dns', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'http', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'tls', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'alert', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'test', 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('alert_types')->insert([
            ['name' => 'Data Leak', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Suspicious Connection', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Malware Activity', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Unauthorized Access', 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('process_catalog')->insert([
            ['process_name' => 'chrome.exe', 'created_at' => $now, 'updated_at' => $now],
            ['process_name' => 'firefox.exe', 'created_at' => $now, 'updated_at' => $now],
            ['process_name' => 'powershell.exe', 'created_at' => $now, 'updated_at' => $now],
            ['process_name' => 'cmd.exe', 'created_at' => $now, 'updated_at' => $now],
            ['process_name' => 'python.exe', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dlds_events');
        Schema::dropIfExists('process_catalog');
        Schema::dropIfExists('alert_types');
        Schema::dropIfExists('event_types');
        Schema::dropIfExists('severity_levels');
    }
};
