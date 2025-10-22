<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_orders', function (Blueprint $t) {
            $t->id();
            $t->uuid('company_id');
            $t->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $t->string('tx_id')->unique();
            $t->foreignId('customer_id')->constrained('customers');
            $t->enum('status', ['Draft', 'Confirmed', 'Cancelled']);
            $t->enum('payment_state', ['PendingReceipt', 'Received'])->default('PendingReceipt');
            $t->enum('payment_method', ['cash', 'transfer'])->nullable();
            $t->decimal('subtotal', 12, 2)->default(0);
            $t->decimal('discount_total', 12, 2)->default(0);
            $t->decimal('tax_total', 12, 2)->default(0);
            $t->decimal('grand_total', 12, 2)->default(0);
            $t->json('attributes')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users');
            $t->foreignId('approved_by')->nullable()->constrained('users');
            $t->timestamps();
            $t->index(['company_id', 'created_at']);
        });

        Schema::create('sale_order_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('sale_order_id')->constrained('sale_orders')->cascadeOnDelete();
            $t->foreignId('product_id')->constrained('products');
            $t->decimal('qty', 14, 3);
            $t->string('uom')->default('unit');
            $t->decimal('unit_price', 12, 2);
            $t->decimal('discount_value', 12, 2)->default(0);
            $t->decimal('tax_rate', 5, 2)->default(0);
            $t->decimal('line_total', 12, 2);
            $t->json('attributes')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_order_items');
        Schema::dropIfExists('sale_orders');
    }
};
