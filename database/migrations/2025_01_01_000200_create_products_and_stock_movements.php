<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->uuid('company_id');
            $t->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $t->string('sku');
            $t->string('name');
            $t->string('base_uom')->default('unit');
            $t->decimal('price', 12, 2);
            $t->decimal('cost', 12, 2)->default(0);
            $t->decimal('stock_qty', 14, 3)->default(0);
            $t->json('attributes')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['company_id', 'sku']);
            $t->index(['company_id', 'created_at']);
        });

        Schema::create('stock_movements', function (Blueprint $t) {
            $t->id();
            $t->uuid('company_id');
            $t->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $t->foreignId('product_id')->constrained('products');
            $t->enum('ref_type', ['SALE', 'ADJUSTMENT', 'OPENING', 'RETURN', 'PURCHASE']);
            $t->string('ref_id')->nullable();
            $t->decimal('qty_in', 14, 3)->default(0);
            $t->decimal('qty_out', 14, 3)->default(0);
            $t->decimal('balance_after', 14, 3);
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->index(['company_id', 'product_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('products');
    }
};
