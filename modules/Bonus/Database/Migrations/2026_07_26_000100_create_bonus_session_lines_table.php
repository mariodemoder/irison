<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bonus_session_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->onDelete('cascade');
            $table->foreignId('bonus_id')->constrained()->onDelete('cascade');
            $table->foreignId('appointment_type_id')->constrained()->onDelete('cascade');
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedInteger('remaining_quantity')->default(1);
            $table->timestamps();

            $table->unique(['bonus_id', 'appointment_type_id'], 'bsl_bonus_type_unique');
            $table->index('bonus_id');
            $table->index('appointment_type_id');
            $table->index('clinic_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bonus_session_lines');
    }
};
