<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geofences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->string('name');
            $table->enum('type', ['polygon', 'circle']);
            $table->json('coordinates'); // keep your coordinate array for logic
            $table->integer('radius_meters')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('alert_on_entry')->default(true);
            $table->boolean('alert_on_exit')->default(true);
            $table->time('curfew_start')->nullable();
            $table->time('curfew_end')->nullable();
            $table->geometry('geometry')->nullable(); // MySQL supports GEOMETRY type
            $table->timestamps();

            $table->index('organization_id');
            $table->index('is_active');

            // Foreign key
            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->cascadeOnDelete();
        });

        // MySQL automatically supports spatial indexes, but you can add manually if needed
        // Schema::table('geofences', function(Blueprint $table) {
        //     $table->spatialIndex('geometry');
        // });
    }

    public function down(): void
    {
        Schema::dropIfExists('geofences');
    }
};
