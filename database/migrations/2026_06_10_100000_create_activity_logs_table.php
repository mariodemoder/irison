<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('activity_logs')) {
            return;
        }

        Schema::create('activity_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('event', 100);
            $table->text('description');
            $table->json('metadata')->nullable();
            $table->string('ip', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'event', 'created_at']);
            $table->index(['event', 'created_at']);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('activity_logs')) {
            Schema::drop('activity_logs');
        }
    }
};
