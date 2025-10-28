<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations
     */
    public function up(): void
    {
        // Document counters for per-tenant sequence numbering
        Schema::create('document_counters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->string('doc_type', 50); // SO, PO, INV, etc.
            $table->string('period', 10); // YYYYMM format
            $table->unsignedInteger('last_num')->default(0);
            $table->timestamps();

            // Unique constraint ensures atomic increment per company/type/period
            $table->unique(['company_id', 'doc_type', 'period'], 'idx_doc_counter_unique');
            $table->index(['company_id', 'doc_type'], 'idx_doc_counter_lookup');
        });

        // Payment slips table for transfer payment approval workflow
        Schema::create('payment_slips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('sale_order_id')->constrained()->onDelete('cascade');
            $table->string('slip_path')->nullable(); // Storage path
            $table->string('slip_url')->nullable(); // Public URL
            $table->enum('status', ['Pending', 'Approved', 'Rejected'])->default('Pending');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status'], 'idx_payment_slips_status');
            $table->index('sale_order_id', 'idx_payment_slips_order');
        });

        // Add payment slip fields to sale_orders if not exists
        Schema::table('sale_orders', function (Blueprint $table) {
            $table->string('payment_method', 50)->nullable()->after('payment_state'); // cash, transfer
            $table->text('payment_notes')->nullable()->after('payment_method');
        });
    }

    /**
     * Reverse the migrations
     */
    public function down(): void
    {
        Schema::table('sale_orders', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'payment_notes']);
        });

        Schema::dropIfExists('payment_slips');
        Schema::dropIfExists('document_counters');
    }
};
