<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('professional_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->decimal('cost_per_hour', 8, 2)->default(0);
            $table->timestamps();

            $table->unique(['clinic_id', 'user_id'], 'professional_rates_clinic_user_unique');
            $table->index('clinic_id');
            $table->index('user_id');
        });
    
        }

    public function down(): void
    {
        Schema::dropIfExists('professional_rates');
    }
};
