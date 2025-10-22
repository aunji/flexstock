<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_field_defs', function (Blueprint $t) {
            $t->id();
            $t->uuid('company_id');
            $t->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $t->enum('entity', ['PRODUCT', 'CUSTOMER', 'SALE_ORDER', 'SALE_ORDER_ITEM']);
            $t->string('key');
            $t->string('label');
            $t->enum('data_type', ['text', 'number', 'boolean', 'date', 'select', 'multiselect']);
            $t->boolean('required')->default(false);
            $t->json('options')->nullable();
            $t->json('default_value')->nullable();
            $t->boolean('is_indexed')->default(false);
            $t->string('validation_regex')->nullable();
            $t->timestamps();
            $t->unique(['company_id', 'entity', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_field_defs');
    }
};
