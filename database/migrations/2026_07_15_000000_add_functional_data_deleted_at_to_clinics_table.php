<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('clinics', 'functional_data_deleted_at')) {
            return;
        }

        Schema::table('clinics', function (Blueprint $table): void {
            $table->timestamp('functional_data_deleted_at')->nullable()->after('churned_at');
        });
    }

    public function down(): void
    {
        Schema::table('clinics', function (Blueprint $table): void {
            $table->dropColumn('functional_data_deleted_at');
        });
    }
};
