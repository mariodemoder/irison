<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class AddTypeToPayments extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        if (! Schema::hasTable('payments')) return;

        if (! Schema::hasColumn('payments', 'type')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->enum('type', ['single','bonus'])->default('single');
            });
        }
        if (! Schema::hasColumn('appointments', 'payment_type')) {
            Schema::table('appointments', function (Blueprint $table) {
                $table->enum('payment_type', ['single','bonus'])->default('single');
                $table->unsignedBigInteger('bonus_id')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        if (! Schema::hasTable('payments')) return;

        if (Schema::hasColumn('payments', 'type')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropColumn('type');
            });
        }
        if (Schema::hasColumn('appointments', 'payment_type')) {
            Schema::table('appointments', function (Blueprint $table) {
                $table->dropColumn(['payment_type','bonus_id']);
            });
        }
    }
}
