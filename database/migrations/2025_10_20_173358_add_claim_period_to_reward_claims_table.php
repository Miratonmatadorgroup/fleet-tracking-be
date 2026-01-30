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
        Schema::table('reward_claims', function (Blueprint $table) {
            $table->date('claim_period')->nullable();

            $table->unique(['reward_campaign_id', 'driver_id', 'claim_period'], 'unique_claim_per_period');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reward_claims', function (Blueprint $table) {
            $table->dropUnique('unique_claim_per_period');
            $table->dropColumn('claim_period');
        });
    }
};
