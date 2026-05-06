<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('bonuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('total_sessions');
            $table->unsignedInteger('remaining_sessions');
            $table->decimal('price', 10, 2)->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['clinic_id']);
            $table->index(['patient_id']);
            $table->index(['expires_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('bonuses');
    }
};
