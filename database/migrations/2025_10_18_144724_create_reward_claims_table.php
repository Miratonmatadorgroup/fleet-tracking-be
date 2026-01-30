<?php

use App\Enums\RewardClaimStatusEnums;
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
        Schema::create('reward_claims', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('reward_campaign_id');
            $table->uuid('driver_id');
            $table->decimal('amount', 12, 2);
            $table->string('status')->default(RewardClaimStatusEnums::PENDING->value);
            $table->uuid('wallet_transaction_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['reward_campaign_id', 'driver_id']);

            // Foreign keys
            $table->foreign('reward_campaign_id')
                ->references('id')
                ->on('reward_campaigns')
                ->onDelete('cascade');

            $table->foreign('driver_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reward_claims');
    }
};
