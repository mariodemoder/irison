<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->string('password')->nullable()->after('email');
            $table->timestamp('email_verified_at')->nullable()->after('password');
            $table->timestamp('last_login_at')->nullable()->after('email_verified_at');
            $table->string('status')->default('inactive')->after('last_login_at');
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn(['password', 'email_verified_at', 'last_login_at', 'status']);
        });
    }
};
