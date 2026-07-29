<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bonus_usages', function (Blueprint $table) {
            $table->foreignId('appointment_type_id')->nullable()->after('notes')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bonus_usages', function (Blueprint $table) {
            $table->dropForeign(['appointment_type_id']);
            $table->dropColumn('appointment_type_id');
        });
    }
};
