<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('trial_journey_events')) {
            return;
        }

        Schema::create('trial_journey_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->string('event_key', 60);
            $table->timestamp('sent_at')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->unique(['clinic_id', 'event_key']);
            $table->index(['event_key']);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('trial_journey_events')) {
            Schema::drop('trial_journey_events');
        }
    }
};
