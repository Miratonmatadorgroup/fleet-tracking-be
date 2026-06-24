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
        Schema::create('geofence_states', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('asset_id');
            $table->uuid('geofence_id');

            $table->boolean('is_inside');

            $table->unique(['asset_id', 'geofence_id']);

            $table->foreign('asset_id')
                ->references('id')
                ->on('assets')
                ->cascadeOnDelete();

            $table->foreign('geofence_id')
                ->references('id')
                ->on('geofences')
                ->cascadeOnDelete();

                $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('geofence_states');
    }
};
