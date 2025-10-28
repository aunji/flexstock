<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations - Add composite indexes and foreign key constraints
     */
    public function up(): void
    {
        // Products: composite index on (company_id, sku) for fast tenant-scoped lookups
        Schema::table('products', function (Blueprint $table) {
            $table->index(['company_id', 'sku'], 'idx_products_company_sku');
            $table->index(['company_id', 'is_active'], 'idx_products_company_active');

            // Foreign key to companies
            $table->foreign('company_id')
                ->references('id')
                ->on('companies')
                ->onDelete('cascade');
        });

        // Customers: composite index on (company_id, phone) for uniqueness + performance
        Schema::table('customers', function (Blueprint $table) {
            $table->index(['company_id', 'phone'], 'idx_customers_company_phone');

            $table->foreign('company_id')
                ->references('id')
                ->on('companies')
                ->onDelete('cascade');

            $table->foreign('tier_id')
                ->references('id')
                ->on('customer_tiers')
                ->onDelete('set null');
        });

        // Customer Tiers
        Schema::table('customer_tiers', function (Blueprint $table) {
            $table->foreign('company_id')
                ->references('id')
                ->on('companies')
                ->onDelete('cascade');
        });

        // Stock Movements: composite index on (company_id, product_id, created_at) for ledger queries
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->index(['company_id', 'product_id', 'created_at'], 'idx_stock_movements_ledger');
            $table->index(['ref_type', 'ref_id'], 'idx_stock_movements_ref');

            $table->foreign('company_id')
                ->references('id')
                ->on('companies')
                ->onDelete('cascade');

            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->onDelete('cascade');
        });

        // Sale Orders: indexes for status-based queries and reports
        Schema::table('sale_orders', function (Blueprint $table) {
            $table->index(['company_id', 'status', 'payment_state'], 'idx_sale_orders_reporting');
            $table->index(['company_id', 'created_at'], 'idx_sale_orders_company_date');
            $table->index('tx_id', 'idx_sale_orders_tx_id');

            $table->foreign('company_id')
                ->references('id')
                ->on('companies')
                ->onDelete('cascade');

            $table->foreign('customer_id')
                ->references('id')
                ->on('customers')
                ->onDelete('restrict');
        });

        // Sale Order Items
        Schema::table('sale_order_items', function (Blueprint $table) {
            $table->index(['sale_order_id', 'product_id'], 'idx_sale_items_order_product');

            $table->foreign('sale_order_id')
                ->references('id')
                ->on('sale_orders')
                ->onDelete('cascade');

            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->onDelete('restrict');
        });

        // Custom Field Definitions
        Schema::table('custom_field_defs', function (Blueprint $table) {
            $table->index(['company_id', 'applies_to'], 'idx_custom_fields_company_entity');

            $table->foreign('company_id')
                ->references('id')
                ->on('companies')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations
     */
    public function down(): void
    {
        // Drop foreign keys first, then indexes
        Schema::table('custom_field_defs', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropIndex('idx_custom_fields_company_entity');
        });

        Schema::table('sale_order_items', function (Blueprint $table) {
            $table->dropForeign(['sale_order_id']);
            $table->dropForeign(['product_id']);
            $table->dropIndex('idx_sale_items_order_product');
        });

        Schema::table('sale_orders', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropForeign(['customer_id']);
            $table->dropIndex('idx_sale_orders_reporting');
            $table->dropIndex('idx_sale_orders_company_date');
            $table->dropIndex('idx_sale_orders_tx_id');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropForeign(['product_id']);
            $table->dropIndex('idx_stock_movements_ledger');
            $table->dropIndex('idx_stock_movements_ref');
        });

        Schema::table('customer_tiers', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropForeign(['tier_id']);
            $table->dropIndex('idx_customers_company_phone');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropIndex('idx_products_company_sku');
            $table->dropIndex('idx_products_company_active');
        });
    }
};
