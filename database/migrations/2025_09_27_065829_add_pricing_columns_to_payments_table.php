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
        Schema::table('payments', function (Blueprint $table) {
             $table->decimal('original_price', 12, 2)->nullable()->after('amount');
            $table->decimal('final_price', 12, 2)->nullable()->after('original_price');
            $table->decimal('subsidy_amount', 12, 2)->nullable()->after('final_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['original_price', 'final_price', 'subsidy_amount']);
        });
    }
};
