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
        Schema::create('tracker_locations', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('tracker_id')
                ->nullable()
                ->constrained('trackers')
                ->nullOnDelete();

            $table->string('imei')->index();

            $table->decimal('latitude', 10, 7);

            $table->decimal('longitude', 10, 7);

            $table->decimal('speed', 8, 2)->nullable();

            $table->timestamp('tracker_time')->nullable();

            $table->longText('raw_packet')->nullable();

            $table->timestamps();

            $table->index(['tracker_id', 'tracker_time']);

            $table->index(['latitude', 'longitude']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tracker_locations');
    }
};
