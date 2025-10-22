<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email')->unique();
            $t->string('password');
            $t->rememberToken();
            $t->timestamps();
        });

        Schema::create('company_user', function (Blueprint $t) {
            $t->uuid('company_id');
            $t->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->enum('role', ['admin', 'cashier', 'viewer']);
            $t->boolean('is_default')->default(false);
            $t->timestamps();
            $t->primary(['company_id', 'user_id']);
            $t->index(['company_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_user');
        Schema::dropIfExists('users');
    }
};
