<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fuel_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Use UUID for asset_id
            $table->uuid('asset_id');

            // Foreign key constraint
            $table->foreign('asset_id')->references('id')->on('assets')->cascadeOnDelete();

            $table->timestamp('trip_start');
            $table->timestamp('trip_end');
            $table->decimal('distance_km', 10, 2);
            $table->decimal('idle_hours', 6, 2)->default(0);
            $table->decimal('speeding_km', 10, 2)->default(0);
            $table->decimal('base_fuel', 10, 2)->nullable();
            $table->decimal('idle_fuel', 10, 2)->nullable();
            $table->decimal('speeding_fuel', 10, 2)->nullable();
            $table->decimal('fuel_consumed_liters', 10, 2);
            $table->decimal('avg_speed', 6, 2)->nullable();
            $table->decimal('max_speed', 6, 2)->nullable();
            $table->timestamps();

            $table->index('asset_id');
            $table->index('trip_start');
            $table->index(['asset_id', 'trip_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_reports');
    }
};

