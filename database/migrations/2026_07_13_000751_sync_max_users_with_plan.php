<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('clinics')
            ->where('plan', 'basic')
            ->orWhereNull('plan')
            ->update(['max_users' => 3]);

        DB::table('clinics')
            ->where('plan', 'pro')
            ->update(['max_users' => 6]);

        DB::table('clinics')
            ->where('plan', 'enterprise')
            ->update(['max_users' => 10]);
    }

    public function down(): void
    {
        // No podemos revertir valores anteriores, no hay pérdida de datos.
    }
};
