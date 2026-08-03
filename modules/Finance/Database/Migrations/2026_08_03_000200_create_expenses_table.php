<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinic_id')->constrained()->onDelete('cascade');
            $table->foreignId('category_id')->nullable()->constrained('expense_categories')->nullOnDelete();
            $table->string('concept', 255);
            $table->string('supplier', 255)->nullable();
            $table->decimal('amount', 10, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->date('date')->nullable();
            $table->string('payment_method', 30)->nullable();
            $table->string('receipt_number', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('clinic_id');
            $table->index('category_id');
            $table->index(['clinic_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
