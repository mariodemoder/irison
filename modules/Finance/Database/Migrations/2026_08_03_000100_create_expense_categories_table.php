<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->onDelete('cascade');
            $table->string('name', 150);
            $table->string('color', 20)->nullable();
            $table->string('description', 255)->nullable();
            $table->timestamps();

            $table->unique(['clinic_id', 'name'], 'expense_categories_clinic_name_unique');
            $table->index('clinic_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_categories');
    }
};
