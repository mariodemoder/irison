<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bonus_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->onDelete('cascade');
            $table->string('description')->nullable();
            $table->integer('sessions')->default(1);
            $table->decimal('price', 10, 2)->default(0);
            $table->timestamps();
            $table->index('clinic_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bonus_types');
    }
};
