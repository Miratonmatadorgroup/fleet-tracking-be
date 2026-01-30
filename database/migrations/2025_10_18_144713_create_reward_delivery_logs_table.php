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
        Schema::create('reward_delivery_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('reward_campaign_id');
            $table->uuid('driver_id'); // user ID for the driver
            $table->uuid('delivery_id');
            $table->decimal('distance_km', 8, 2)->nullable();
            $table->decimal('weighted_count', 8, 4)->default(0); // e.g., 1.0 or 0.5 based on weighting rules
            $table->decimal('delivery_earning', 12, 2)->default(0);
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
        Schema::dropIfExists('reward_delivery_logs');
    }
};
