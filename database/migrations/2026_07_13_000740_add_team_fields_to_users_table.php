<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('profile_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('profession_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('allow_online_booking')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['profile_id']);
            $table->dropForeign(['profession_id']);
            $table->dropColumn(['profile_id', 'profession_id', 'allow_online_booking']);
        });
    }
};
