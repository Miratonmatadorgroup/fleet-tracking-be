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
        Schema::create('fund_reconciliations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('api_client_id');


            // amounts
            $table->decimal('total_amount_owed', 15, 2)->default(0);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->decimal('balance_owed', 15, 2)->default(0);
            $table->decimal('received_amount', 15, 2)->default(0);

            // status with default enum value
            $table->string('status')->default(\App\Enums\FundsReconcilationStatusEnums::NOT_PAID_OFF);


            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fund_reconciliations');
    }
};
