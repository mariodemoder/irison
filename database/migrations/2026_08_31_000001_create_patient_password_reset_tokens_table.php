<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tokens de restablecimiento de contraseña del Portal del Paciente.
     *
     * Se keyan por `email` (que almacena el patient_id, ver
     * Patient::getEmailForPasswordReset()) en lugar del email crudo, de modo
     * que cada clinic-patient tenga su propio token independiente. Un email
     * compartido entre clínicas ya no invalida el token de la otra.
     */
    public function up(): void
    {
        Schema::create('patient_password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_password_reset_tokens');
    }
};
