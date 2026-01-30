<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->onDelete('cascade');
            $table->decimal('available_balance', 15, 2)->default(0.00);
            $table->decimal('total_balance', 15, 2)->default(0.00); // includes pending
            $table->string('account_number')->unique();
            $table->string('bank_name')->nullable();
            $table->string('currency')->default('NGN');
            $table->boolean('is_virtual_account')->default(false); 
            $table->string('provider')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
