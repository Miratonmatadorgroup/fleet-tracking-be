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
        Schema::create('ride_pooling_pricings', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('category')->unique(); // e.g., 'airport_pickup', 'suv', 'car', etc.

            $table->decimal('base_price', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ride_pooling_pricings');
    }
};
