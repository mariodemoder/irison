<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('providers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->onDelete('cascade');
            $table->string('name', 255);
            $table->string('nif', 30)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('address', 500)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['clinic_id', 'name'], 'providers_clinic_name_unique');
            $table->index('clinic_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('providers');
    }
};
