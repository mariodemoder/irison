<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | clinics
        |--------------------------------------------------------------------------
        */
        Schema::dropIfExists('clinics');
        Schema::create('clinics', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('timezone')->default('Europe/Madrid');
            $table->timestamps();
        });

        /*
        |--------------------------------------------------------------------------
        | users
        |--------------------------------------------------------------------------
        */
        Schema::dropIfExists('users');
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password_hash');
            $table->enum('role', ['owner', 'staff'])->default('owner');
            $table->timestamps();
        });

        /*
        |--------------------------------------------------------------------------
        | subscriptions
        |--------------------------------------------------------------------------
        */
        Schema::dropIfExists('subscriptions');
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['trial', 'active', 'past_due', 'canceled'])->default('trial');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->string('stripe_customer_id')->nullable();
            $table->string('stripe_subscription_id')->nullable();
            $table->timestamps();
        });

        /*
        |--------------------------------------------------------------------------
        | patients
        |--------------------------------------------------------------------------
        */
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->date('birth_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['clinic_id', 'last_name']);
        });

        /*
        |--------------------------------------------------------------------------
        | appointments
        |--------------------------------------------------------------------------
        */
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->timestamp('start_time');
            $table->timestamp('end_time')->nullable();
            $table->enum('status', ['scheduled', 'completed', 'canceled', 'no_show'])->default('scheduled');
            $table->enum('payment_status', ['pending', 'paid', 'covered_by_pack'])->default('pending');
            $table->timestamps();

            $table->index(['clinic_id', 'start_time']);
        });

        /*
        |--------------------------------------------------------------------------
        | clinical_records
        |--------------------------------------------------------------------------
        */
        Schema::create('clinical_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['clinic_id', 'patient_id']);
        });

        /*
        |--------------------------------------------------------------------------
        | packs
        |--------------------------------------------------------------------------
        */
        Schema::create('packs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('total_sessions');
            $table->unsignedInteger('remaining_sessions');
            $table->decimal('price', 10, 2);
            $table->enum('status', ['active', 'exhausted', 'expired'])->default('active');
            $table->timestamps();

            $table->index(['clinic_id', 'patient_id']);
        });

        /*
        |--------------------------------------------------------------------------
        | payments
        |--------------------------------------------------------------------------
        */
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('pack_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 10, 2);
            $table->enum('method', ['cash', 'card', 'transfer'])->default('cash');
            $table->enum('status', ['paid', 'pending'])->default('paid');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['clinic_id', 'patient_id']);
        });

        /*
        |--------------------------------------------------------------------------
        | reminders
        |--------------------------------------------------------------------------
        */
        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->enum('channel', ['email', 'whatsapp']);
            $table->timestamp('sent_at')->nullable();
            $table->enum('status', ['sent', 'failed'])->default('sent');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminders');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('packs');
        Schema::dropIfExists('clinical_records');
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('patients');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('users');
        Schema::dropIfExists('clinics');
    }
};
