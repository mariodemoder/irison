<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('bonus_usages', function (Blueprint $table) {
            $table->foreignId('clinic_id')->after('id')->constrained()->cascadeOnDelete();
            $table->index(['clinic_id']);
        });
    }

    public function down()
    {
        Schema::table('bonus_usages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('clinic_id');
        });
    }
};
