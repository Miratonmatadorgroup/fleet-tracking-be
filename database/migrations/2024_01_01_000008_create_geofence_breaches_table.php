<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geofence_breaches', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Use uuid() instead of foreignId()
            $table->uuid('asset_id');
            $table->uuid('geofence_id');

            $table->enum('breach_type', ['entry', 'exit', 'curfew']);
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->timestamp('timestamp');
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('asset_id')->references('id')->on('assets')->cascadeOnDelete();
            $table->foreign('geofence_id')->references('id')->on('geofences')->cascadeOnDelete();

            $table->index('asset_id');
            $table->index('geofence_id');
            $table->index('timestamp');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geofence_breaches');
    }
};

