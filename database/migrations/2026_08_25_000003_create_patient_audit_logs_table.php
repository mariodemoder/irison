<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('clinic_id');
            $table->unsignedBigInteger('patient_id');
            $table->string('event');
            $table->text('description')->nullable();
            $table->json('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['clinic_id', 'patient_id']);
            $table->index('event');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_audit_logs');
    }
};
