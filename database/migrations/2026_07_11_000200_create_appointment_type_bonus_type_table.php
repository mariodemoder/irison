<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_type_bonus_type', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bonus_type_id')->constrained('bonus_types')->onDelete('cascade');
            $table->foreignId('appointment_type_id')->constrained('appointment_types')->onDelete('cascade');
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->timestamps();

            $table->unique(['bonus_type_id', 'appointment_type_id'], 'bt_at_unique');
            $table->index('bonus_type_id');
            $table->index('appointment_type_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_type_bonus_type');
    }
};
