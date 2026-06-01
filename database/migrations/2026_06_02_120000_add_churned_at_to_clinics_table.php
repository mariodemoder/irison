<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('clinics') || Schema::hasColumn('clinics', 'churned_at')) {
            return;
        }

        Schema::table('clinics', function (Blueprint $table): void {
            $table->timestamp('churned_at')->nullable()->after('suspended_at');
            $table->index('churned_at');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('clinics') || ! Schema::hasColumn('clinics', 'churned_at')) {
            return;
        }

        Schema::table('clinics', function (Blueprint $table): void {
            $table->dropIndex(['churned_at']);
            $table->dropColumn('churned_at');
        });
    }
};
