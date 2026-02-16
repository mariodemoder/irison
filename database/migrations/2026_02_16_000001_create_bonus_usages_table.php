<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('bonus_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bonus_id')->constrained('bonuses')->cascadeOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->timestamp('used_at');
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['bonus_id', 'appointment_id']);
            $table->index(['used_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('bonus_usages');
    }
};
