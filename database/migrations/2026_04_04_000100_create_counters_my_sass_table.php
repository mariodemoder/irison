<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('counters_my_sass')) {
            return;
        }

        Schema::create('counters_my_sass', function (Blueprint $table) {
            $table->id();
            $table->string('prefix', 4);
            $table->unsignedBigInteger('last_number')->default(0);
            $table->string('table_type', 50);
            $table->timestamps();

            $table->unique('table_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('counters_my_sass');
    }
};