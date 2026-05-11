<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add 'varios' to typeinvoice if mysql ENUM
        $driver = DB::getDriverName();
        if ($driver === 'mysql' && Schema::hasTable('documents') && Schema::hasColumn('documents', 'typeinvoice')) {
            $columnType = DB::selectOne("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documents' AND COLUMN_NAME = 'typeinvoice'");
            if ($columnType && str_contains((string) $columnType->COLUMN_TYPE, 'enum')) {
                DB::statement("ALTER TABLE `documents` MODIFY `typeinvoice` ENUM('appointment','package','credit','manual','varios') NOT NULL DEFAULT 'varios'");
            }
        }

        // 2. Create document_items table
        if (!Schema::hasTable('document_items')) {
            Schema::create('document_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();

                $table->string('type', 50); // appointment | bonus | product | manual
                $table->unsignedBigInteger('reference_id')->nullable(); // appointment_id | bonus_id | product_id

                $table->string('description', 500);
                $table->decimal('quantity', 10, 4)->default(1);
                $table->decimal('unit_price', 10, 2)->default(0);
                $table->decimal('tax_rate', 5, 2)->default(0);   // % IVA venta
                $table->decimal('buy_price', 10, 2)->default(0);
                $table->decimal('buy_tax', 5, 2)->default(0);    // % IVA compra
                $table->decimal('total', 10, 2)->default(0);     // quantity * unit_price * (1 + tax_rate/100)

                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['document_id', 'type']);
                $table->index(['type', 'reference_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('document_items');

        $driver = DB::getDriverName();
        if ($driver === 'mysql' && Schema::hasTable('documents') && Schema::hasColumn('documents', 'typeinvoice')) {
            $columnType = DB::selectOne("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'documents' AND COLUMN_NAME = 'typeinvoice'");
            if ($columnType && str_contains((string) $columnType->COLUMN_TYPE, 'enum')) {
                DB::statement("ALTER TABLE `documents` MODIFY `typeinvoice` ENUM('appointment','package','credit','manual') NOT NULL DEFAULT 'appointment'");
            }
        }
    }
};
