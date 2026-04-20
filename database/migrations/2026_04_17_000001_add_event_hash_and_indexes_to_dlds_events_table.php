<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Indexes and event_hash are now created directly in
        // 2026_04_14_000000_create_dlds_events_table.php.
        // This migration is intentionally kept as a no-op to preserve
        // migration history compatibility.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op.
    }
};
