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
        Schema::create('transport_pricings', function (Blueprint $table) {
            $table->id();
            $table->string('mode_of_transportation');
            $table->string('pickup_location')->nullable();
            $table->string('dropoff_location')->nullable(); 
            $table->decimal('rate_per_kg', 10, 2)->nullable();
            $table->decimal('rate_per_route', 10, 2)->nullable();
            $table->timestamps();
            $table->unique(['mode_of_transportation', 'pickup_location', 'dropoff_location'], 'unique_route_pricing');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transport_pricing');
    }
};
