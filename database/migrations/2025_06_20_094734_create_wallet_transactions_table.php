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
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('wallet_id')->constrained('wallets')->onDelete('cascade');
            $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade');

            $table->string('type'); // 'credit' or 'debit'
            $table->decimal('amount', 15, 2);
            $table->string('description')->nullable(); // e.g. "Transfer to John", "Topup"
            $table->string('reference')->unique(); // e.g. transaction ref

            $table->string('status')->default('pending'); 
            $table->string('method')->nullable(); // "manual", "bank", "transfer", "system"
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
