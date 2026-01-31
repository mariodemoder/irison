<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinics', function (Blueprint $table) {
            $table->string('subscription_provider')->nullable()->after('subscribed_at');
            $table->string('subscription_reference')->nullable()->after('subscription_provider');
        });
    }

    public function down(): void
    {
        Schema::table('clinics', function (Blueprint $table) {
            $table->dropColumn(['subscription_provider', 'subscription_reference']);
        });
    }
};
