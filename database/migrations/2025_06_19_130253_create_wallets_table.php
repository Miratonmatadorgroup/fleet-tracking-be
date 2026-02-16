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
            $table->decimal('total_balance', 15, 2)->default(0.00);
            $table->decimal('pending_balance', 15, 2)->default(0.00);

            $table->string('account_number')->unique();
            $table->string('bank_name')->nullable()->default('LoopFreight Wallet');
            $table->string('currency')->default('NGN');
            $table->boolean('is_virtual_account')->default(false);
            $table->string('provider')->nullable();

            // External bank account details
            $table->string('external_account_id')->nullable();
            $table->string('external_account_number')->nullable();
            $table->string('external_account_name')->nullable();
            $table->string('external_bank')->nullable();
            $table->string('external_reference')->nullable();
            $table->decimal('external_available_balance', 15, 2)->default(0.00);
            $table->decimal('external_book_balance', 15, 2)->default(0.00);

            $table->timestamps();

            // Unique constraint: a user cannot have multiple wallets in the same currency
            $table->unique(['user_id', 'currency']);
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
