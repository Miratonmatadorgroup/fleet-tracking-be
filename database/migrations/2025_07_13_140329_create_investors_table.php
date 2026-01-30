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
        Schema::create('investors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->onDelete('cascade');
            $table->string('full_name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('whatsapp_number')->nullable();
            $table->string('business_name')->nullable();
            $table->text('address')->nullable();
            $table->string('gender')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('account_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('next_of_kin_name')->nullable();
            $table->string('next_of_kin_phone')->nullable();
            $table->string('investment_amount')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('status')->default('inactive');
            $table->string('application_status')->default('review');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('investors');
    }
};
