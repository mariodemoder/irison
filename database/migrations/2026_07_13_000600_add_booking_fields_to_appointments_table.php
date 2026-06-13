<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->string('booking_source')->nullable()->after('custom_type');
            $table->text('booking_notes')->nullable()->after('booking_source');
            $table->string('confirmation_token')->nullable()->unique()->after('booking_notes');
            $table->foreignId('professional_id')->nullable()->constrained('users')->nullOnDelete()->after('confirmation_token');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['professional_id']);
            $table->dropColumn(['booking_source', 'booking_notes', 'confirmation_token', 'professional_id']);
        });
    }
};
