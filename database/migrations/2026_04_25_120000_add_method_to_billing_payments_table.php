<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_payments', function ( $table): void {
            $table->string('method', 50)->default('card')->after('provider_ref');
        });
    }

    public function down(): void
    {
        Schema::table('billing_payments', function (Blueprint $table): void {
            $table->dropColumn('method');
        });
    }
};
