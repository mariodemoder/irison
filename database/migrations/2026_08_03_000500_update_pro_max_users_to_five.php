<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('clinics')
            ->where('plan', 'pro')
            ->update(['max_users' => 5]);
    }

    public function down(): void
    {
        DB::table('clinics')
            ->where('plan', 'pro')
            ->update(['max_users' => 10]);
    }
};
