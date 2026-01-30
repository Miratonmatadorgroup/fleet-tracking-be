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
        Schema::table('deliveries', function (Blueprint $table) {
            // Coordinates
            $table->decimal('pickup_latitude', 10, 7)->nullable()->after('pickup_location');
            $table->decimal('pickup_longitude', 10, 7)->nullable()->after('pickup_latitude');
            $table->decimal('dropoff_latitude', 10, 7)->nullable()->after('dropoff_location');
            $table->decimal('dropoff_longitude', 10, 7)->nullable()->after('dropoff_latitude');

            // Distance and duration
            $table->decimal('distance_km', 8, 2)->nullable()->after('dropoff_longitude');
            $table->integer('duration_minutes')->nullable()->after('distance_km');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
           $table->dropColumn([
                'pickup_latitude',
                'pickup_longitude',
                'dropoff_latitude',
                'dropoff_longitude',
                'distance_km',
                'duration_minutes',
            ]);
        });
    }
};
