<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('documents')) {
            return;
        }

        Schema::table('documents', function (Blueprint $table) {
            if (Schema::hasColumn('documents', 'number')) {
                $table->dropColumn('number');
            }

            if (!Schema::hasColumn('documents', 'is_payed')) {
                $table->boolean('is_payed')->default(false)->after('status');
            }

            if (!Schema::hasColumn('documents', 'is_sended')) {
                $table->boolean('is_sended')->default(false)->after('is_payed');
            }

            if (!Schema::hasColumn('documents', 'clinic_name')) {
                $table->string('clinic_name', 255)->nullable()->after('counter');
            }

            if (!Schema::hasColumn('documents', 'clinic_nif')) {
                $table->string('clinic_nif', 50)->nullable()->after('clinic_name');
            }

            if (!Schema::hasColumn('documents', 'clinic_address')) {
                $table->string('clinic_address', 255)->nullable()->after('clinic_nif');
            }

            if (!Schema::hasColumn('documents', 'clinic_zip')) {
                $table->string('clinic_zip', 20)->nullable()->after('clinic_address');
            }

            if (!Schema::hasColumn('documents', 'clinic_province')) {
                $table->string('clinic_province', 120)->nullable()->after('clinic_zip');
            }

            if (!Schema::hasColumn('documents', 'clinic_country')) {
                $table->string('clinic_country', 120)->nullable()->after('clinic_province');
            }

            if (!Schema::hasColumn('documents', 'user_full_name')) {
                $table->string('user_full_name', 255)->nullable()->after('clinic_country');
            }
        });

        if (Schema::hasColumn('documents', 'status')) {
            DB::statement("UPDATE `documents` SET `status` = 'issued' WHERE `status` IS NULL OR `status` NOT IN ('issued','draft','cancelled')");
            DB::statement("ALTER TABLE `documents` MODIFY `status` ENUM('issued','draft','cancelled') NOT NULL DEFAULT 'issued'");
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('documents')) {
            return;
        }

        Schema::table('documents', function (Blueprint $table) {
            $dropColumns = [];

            foreach ([
                'clinic_name',
                'clinic_nif',
                'clinic_address',
                'clinic_zip',
                'clinic_province',
                'clinic_country',
                'user_full_name',
                'is_payed',
                'is_sended',
            ] as $column) {
                if (Schema::hasColumn('documents', $column)) {
                    $dropColumns[] = $column;
                }
            }

            if (!empty($dropColumns)) {
                $table->dropColumn($dropColumns);
            }

            if (!Schema::hasColumn('documents', 'number')) {
                $table->string('number', 100)->nullable()->after('type_from');
            }
        });

        if (Schema::hasColumn('documents', 'status')) {
            DB::statement("ALTER TABLE `documents` MODIFY `status` ENUM('issued','draft','cancelled') NOT NULL DEFAULT 'issued'");
        }
    }
};
