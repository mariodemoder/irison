<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $table->string('reference', 100);
            $table->string('name');
            $table->decimal('sale_price', 10, 2)->default(0);
            $table->decimal('purchase_price', 10, 2)->default(0);
            $table->decimal('sale_tax', 5, 2)->default(0);
            $table->decimal('purchase_tax', 5, 2)->default(0);
            $table->string('family', 120)->nullable();
            $table->string('lot', 120)->nullable();
            $table->timestamps();

            $table->unique(['clinic_id', 'reference']);
            $table->index(['clinic_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
