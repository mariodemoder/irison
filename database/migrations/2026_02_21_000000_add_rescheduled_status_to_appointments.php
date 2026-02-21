<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add 'rescheduled' to appointments.status enum
        DB::statement("ALTER TABLE `appointments` MODIFY `status` ENUM('scheduled','rescheduled','completed','canceled','no_show') NOT NULL DEFAULT 'scheduled'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to previous enum without 'rescheduled'
        DB::statement("ALTER TABLE `appointments` MODIFY `status` ENUM('scheduled','completed','canceled','no_show') NOT NULL DEFAULT 'scheduled'");
    }
};
