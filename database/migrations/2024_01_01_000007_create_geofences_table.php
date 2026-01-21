<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Enable PostGIS extension
        DB::statement('CREATE EXTENSION IF NOT EXISTS postgis');
        
        Schema::create('geofences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->enum('type', ['polygon', 'circle']);
            $table->json('coordinates');
            $table->integer('radius_meters')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('alert_on_entry')->default(true);
            $table->boolean('alert_on_exit')->default(true);
            $table->time('curfew_start')->nullable();
            $table->time('curfew_end')->nullable();
            $table->timestamps();

            $table->index('organization_id');
            $table->index('is_active');
        });

        // Add PostGIS geometry column
        DB::statement('ALTER TABLE geofences ADD COLUMN geometry GEOMETRY(POLYGON, 4326)');
        DB::statement('CREATE INDEX idx_geofences_geometry ON geofences USING GIST(geometry)');
    }

    public function down(): void
    {
        Schema::dropIfExists('geofences');
    }
};