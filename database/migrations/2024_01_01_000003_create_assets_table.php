<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('driver_id')->nullable();
            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
                ->cascadeOnDelete();

            $table->foreign('driver_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->string('equipment_id', 100)->unique();
            $table->enum('asset_type', ['car', 'bike', 'suv', 'truck', 'van', 'boat', 'helicopter', 'plane', 'ship']);
            $table->enum('class', ['A', 'B', 'C']);
            $table->string('make', 100)->nullable();
            $table->string('model', 100)->nullable();
            $table->integer('year')->nullable();
            $table->string('license_plate', 50)->nullable();
            $table->string('vin', 50)->nullable();
            $table->string('color', 50)->nullable();
            $table->text('image_url')->nullable();
            $table->text('driver_image_url')->nullable();
            $table->enum('status', ['active', 'idle', 'offline', 'maintenance'])->default('offline');
            $table->decimal('base_consumption_rate', 5, 2)->nullable()->comment('L/km');
            $table->decimal('idle_consumption_rate', 5, 2)->nullable()->comment('L/hour');
            $table->decimal('speeding_penalty', 3, 2)->default(0.15)->comment('Percentage penalty');
            $table->decimal('last_known_lat', 10, 8)->nullable();
            $table->decimal('last_known_lng', 11, 8)->nullable();
            $table->timestamp('last_ping_at')->nullable();
            $table->timestamps();

            $table->index('organization_id');
            $table->index('equipment_id');
            $table->index('status');
            $table->index('class');
            $table->index('last_ping_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
