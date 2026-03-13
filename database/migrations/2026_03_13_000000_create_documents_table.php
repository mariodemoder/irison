<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('documents')) {
            Schema::create('documents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
                $table->foreignId('patient_id')->constrained()->cascadeOnDelete();

                $table->enum('type', ['invoice', 'abono', 'receipt', 'credit_note']);
                $table->enum('type_from', ['appointment', 'package', 'credit', 'manual', 'invoice', 'abono', 'receipt', 'credit_note'])->nullable();
                $table->string('number', 100);
                $table->unsignedBigInteger('from_id')->nullable();
                $table->enum('typeinvoice', ['appointment', 'package', 'credit', 'manual']);

                $table->string('patient_nif', 50)->nullable();
                $table->string('patient_full_name', 255)->nullable();
                $table->string('patient_email', 255)->nullable();
                $table->string('patient_phone', 50)->nullable();
                $table->string('patient_address', 255)->nullable();
                $table->string('patient_zip', 20)->nullable();

                $table->date('date');
                $table->decimal('amount', 10, 2);
                $table->text('notes')->nullable();

                $table->enum('status', ['issued', 'draft', 'cancelled'])->default('issued');

                $table->timestamp('created_at')->useCurrent();

                $table->index(['clinic_id', 'patient_id']);
                $table->index(['type']);
                $table->index(['status']);
            });
        }

        if (Schema::hasTable('patients')) {
            Schema::table('patients', function (Blueprint $table) {
                if (!Schema::hasColumn('patients', 'address')) {
                    $table->string('address', 255)->nullable();
                }

                if (!Schema::hasColumn('patients', 'zip')) {
                    $table->string('zip', 20)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('patients')) {
            Schema::table('patients', function (Blueprint $table) {
                $dropColumns = [];

                if (Schema::hasColumn('patients', 'address')) {
                    $dropColumns[] = 'address';
                }

                if (Schema::hasColumn('patients', 'zip')) {
                    $dropColumns[] = 'zip';
                }

                if (!empty($dropColumns)) {
                    $table->dropColumn($dropColumns);
                }
            });
        }

        Schema::dropIfExists('documents');
    }
};
