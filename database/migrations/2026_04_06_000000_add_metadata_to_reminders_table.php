<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reminders', function (Blueprint $table): void {
            $table->string('reminder_type', 10)->nullable()->after('channel');
            $table->string('recipient_email')->nullable()->after('reminder_type');
            $table->text('error_message')->nullable()->after('recipient_email');
            $table->index('reminder_type');
        });
    }

    public function down(): void
    {
        Schema::table('reminders', function (Blueprint $table): void {
            $table->dropIndex(['reminder_type']);
            $table->dropColumn(['reminder_type', 'recipient_email', 'error_message']);
        });
    }
};
