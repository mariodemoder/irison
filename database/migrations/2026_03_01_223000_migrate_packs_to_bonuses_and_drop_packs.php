<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('payments')) {
            return;
        }

        $packToBonusMap = [];

        if (Schema::hasTable('packs')) {
            $packs = DB::table('packs')->orderBy('id')->get();

            foreach ($packs as $pack) {
                $existing = DB::table('bonuses')
                    ->where('clinic_id', $pack->clinic_id)
                    ->where('patient_id', $pack->patient_id)
                    ->where('total_sessions', $pack->total_sessions)
                    ->where('remaining_sessions', $pack->remaining_sessions)
                    ->where('price', $pack->price)
                    ->orderBy('id')
                    ->first();

                if ($existing) {
                    $packToBonusMap[(int) $pack->id] = (int) $existing->id;
                    continue;
                }

                $bonusId = DB::table('bonuses')->insertGetId([
                    'clinic_id' => $pack->clinic_id,
                    'patient_id' => $pack->patient_id,
                    'name' => 'Bono migrado #' . $pack->id,
                    'total_sessions' => $pack->total_sessions,
                    'remaining_sessions' => $pack->remaining_sessions,
                    'price' => $pack->price,
                    'expires_at' => null,
                    'created_at' => $pack->created_at ?? now(),
                    'updated_at' => $pack->updated_at ?? now(),
                ]);

                $packToBonusMap[(int) $pack->id] = (int) $bonusId;
            }
        }

        if (Schema::hasColumn('payments', 'package_id')) {
            Schema::table('payments', function (Blueprint $table) {
                try {
                    $table->dropForeign(['package_id']);
                } catch (\Throwable $e) {
                    // ignore if foreign key does not exist
                }
            });

            foreach ($packToBonusMap as $packId => $bonusId) {
                DB::table('payments')
                    ->where('package_id', $packId)
                    ->update(['package_id' => $bonusId]);
            }

            Schema::table('payments', function (Blueprint $table) {
                $table->foreign('package_id')->references('id')->on('bonuses')->nullOnDelete();
            });
        }

        if (Schema::hasTable('packs')) {
            Schema::drop('packs');
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('packs')) {
            Schema::create('packs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
                $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
                $table->unsignedInteger('total_sessions');
                $table->unsignedInteger('remaining_sessions');
                $table->decimal('price', 10, 2);
                $table->enum('status', ['active', 'exhausted', 'expired'])->default('active');
                $table->timestamps();

                $table->index(['clinic_id', 'patient_id']);
            });
        }

        if (Schema::hasColumn('payments', 'package_id')) {
            Schema::table('payments', function (Blueprint $table) {
                try {
                    $table->dropForeign(['package_id']);
                } catch (\Throwable $e) {
                    // ignore
                }
            });

            Schema::table('payments', function (Blueprint $table) {
                $table->foreign('package_id')->references('id')->on('packs')->nullOnDelete();
            });
        }
    }
};
