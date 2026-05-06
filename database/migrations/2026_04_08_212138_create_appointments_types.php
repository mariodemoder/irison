<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('appointment_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->onDelete('cascade');
            $table->string('description')->nullable();
            $table->integer('estimated_hours')->default(0);
            $table->integer('estimated_minutes')->default(60);
            $table->decimal('price', 10, 2)->default(0);
            $table->enum('payment_type', ['simple', 'abono'])->default('simple');
            $table->timestamps();
            $table->index('clinic_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointment_types');
    }
};
