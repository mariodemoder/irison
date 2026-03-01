<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('credit_usages')) {
            return;
        }

        Schema::table('credit_usages', function (Blueprint $table) {
            if (!Schema::hasColumn('credit_usages', 'payment_id')) {
                $table->foreignId('payment_id')->nullable()->after('appointment_id')->constrained('payments')->nullOnDelete();
                $table->index(['payment_id']);
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('credit_usages') || !Schema::hasColumn('credit_usages', 'payment_id')) {
            return;
        }

        Schema::table('credit_usages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_id');
        });
    }
};
