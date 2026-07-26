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
        Schema::create('geofence_trackers', function (Blueprint $table) {

            $table->uuid('geofence_id');

            $table->uuid('tracker_id');

            $table->timestamps();

            $table->primary([
                'geofence_id',
                'tracker_id'
            ]);

            $table->foreign('geofence_id')
                ->references('id')
                ->on('geofences')
                ->cascadeOnDelete();

            $table->foreign('tracker_id')
                ->references('id')
                ->on('trackers')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('geofence_trackers');
    }
};
