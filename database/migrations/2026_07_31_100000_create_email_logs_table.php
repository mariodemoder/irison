<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('patient_id')->nullable()->index();
            $table->unsignedBigInteger('appointment_id')->nullable()->index();
            $table->unsignedBigInteger('reminder_id')->nullable()->index();
            $table->string('category', 50)->nullable()->index();
            $table->string('to_email');
            $table->string('from_email')->nullable();
            $table->string('subject')->nullable();
            $table->string('status', 20)->default('sent')->index();
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['clinic_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_logs');
    }
};
