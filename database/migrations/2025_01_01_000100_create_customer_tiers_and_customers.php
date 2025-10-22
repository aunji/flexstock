<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_tiers', function (Blueprint $t) {
            $t->uuid('company_id');
            $t->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $t->string('code');
            $t->string('name');
            $t->enum('discount_type', ['percent', 'amount']);
            $t->decimal('discount_value', 12, 2)->default(0);
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->primary(['company_id', 'code']);
        });

        Schema::create('customers', function (Blueprint $t) {
            $t->id();
            $t->uuid('company_id');
            $t->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $t->string('phone');
            $t->string('name');
            $t->string('tier_code')->nullable();
            $t->json('attributes')->nullable();
            $t->timestamps();
            $t->unique(['company_id', 'phone']);
            $t->foreign(['company_id', 'tier_code'])
                ->references(['company_id', 'code'])->on('customer_tiers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
        Schema::dropIfExists('customer_tiers');
    }
};
