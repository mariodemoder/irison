<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('counters_clinics')) {
            Schema::create('counters_clinics', function (Blueprint $table) {
                $table->id();
                $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
                $table->string('prefix', 4);
                $table->unsignedBigInteger('last_number')->default(0);
                $table->string('table_type', 50);
                $table->timestamps();

                $table->unique(['clinic_id', 'table_type']);
            });
        }

        if (Schema::hasTable('documents')) {
            Schema::table('documents', function (Blueprint $table) {
                if (!Schema::hasColumn('documents', 'counter')) {
                    $table->string('counter', 12)->nullable()->after('number');
                    $table->index(['clinic_id', 'counter']);
                }
            });
        }

        if (Schema::hasTable('billing_payments')) {
            Schema::table('billing_payments', function (Blueprint $table) {
                if (!Schema::hasColumn('billing_payments', 'counter')) {
                    $table->string('counter', 12)->nullable()->after('provider_ref');
                    $table->index(['clinic_id', 'counter']);
                }
            });
        }

        if (Schema::hasTable('bonuses')) {
            Schema::table('bonuses', function (Blueprint $table) {
                if (!Schema::hasColumn('bonuses', 'counter')) {
                    $table->string('counter', 12)->nullable()->after('price');
                    $table->index(['clinic_id', 'counter']);
                }
            });
        }

        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $table) {
                if (!Schema::hasColumn('payments', 'counter')) {
                    $table->string('counter', 12)->nullable()->after('status');
                    $table->index(['clinic_id', 'counter']);
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('documents') && Schema::hasColumn('documents', 'counter')) {
            Schema::table('documents', function (Blueprint $table) {
                $table->dropIndex(['clinic_id', 'counter']);
                $table->dropColumn('counter');
            });
        }

        if (Schema::hasTable('billing_payments') && Schema::hasColumn('billing_payments', 'counter')) {
            Schema::table('billing_payments', function (Blueprint $table) {
                $table->dropIndex(['clinic_id', 'counter']);
                $table->dropColumn('counter');
            });
        }

        if (Schema::hasTable('bonuses') && Schema::hasColumn('bonuses', 'counter')) {
            Schema::table('bonuses', function (Blueprint $table) {
                $table->dropIndex(['clinic_id', 'counter']);
                $table->dropColumn('counter');
            });
        }

        if (Schema::hasTable('payments') && Schema::hasColumn('payments', 'counter')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropIndex(['clinic_id', 'counter']);
                $table->dropColumn('counter');
            });
        }

        Schema::dropIfExists('counters_clinics');
    }
};
