<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('clinics', 'subscription_status')) {
            Schema::table('clinics', function (Blueprint $table) {
                $table->string('subscription_status')->default('inactive')->after('trial_ends_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('clinics', 'subscription_status')) {
            Schema::table('clinics', function (Blueprint $table) {
                $table->dropColumn('subscription_status');
            });
        }
    }
};
