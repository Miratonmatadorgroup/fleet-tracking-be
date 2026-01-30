<?php

use App\Enums\PayoutStatusEnums;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payouts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable();
            $table->uuid('driver_id')->nullable();
            $table->uuid('partner_id')->nullable();
            $table->uuid('investor_id')->nullable();
            $table->decimal('amount', 15, 2);
            $table->string('bank_name');
            $table->string('account_number');
            $table->string('currency', 5)->default('NGN');
            $table->string('status')->default(PayoutStatusEnums::PENDING->value);
            $table->string('provider_reference')->nullable(); 
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payouts');
    }
};
