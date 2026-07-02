<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_requests', function (Blueprint $table) {
            $table->dropForeign(['reviewed_by']);
            $table->foreign('reviewed_by')->references('id')->on('admin_users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('subscription_requests', function (Blueprint $table) {
            $table->dropForeign(['reviewed_by']);
            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
        });
    }
};
