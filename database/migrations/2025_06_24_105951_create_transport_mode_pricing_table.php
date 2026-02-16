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
        Schema::create('transport_mode_pricings', function (Blueprint $table) {
            $table->id();
            $table->string('mode')->unique(); // e.g., 'bike', 'van', etc.
            $table->decimal('price_per_km', 10, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transport_mode_pricing');
    }
};
