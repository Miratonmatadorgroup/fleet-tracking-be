<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gps_logs', function (Blueprint $table) {
            $table->bigIncrements('id'); // auto increment PK works in both
            $table->uuid('asset_id'); // assuming assets.id is UUID
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->decimal('speed', 6, 2)->default(0);
            $table->boolean('ignition')->default(false);
            $table->decimal('heading', 5, 2)->nullable();
            $table->decimal('altitude', 8, 2)->nullable();
            $table->integer('satellites')->nullable();
            $table->decimal('hdop', 4, 2)->nullable();
            $table->timestamp('timestamp');
            $table->timestamps();

            // Foreign key
            $table->foreign('asset_id')
                ->references('id')
                ->on('assets')
                ->cascadeOnDelete();

            // Indexes
            $table->index('asset_id');
            $table->index('timestamp');
            $table->index(['asset_id', 'timestamp']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gps_logs');
    }
};
