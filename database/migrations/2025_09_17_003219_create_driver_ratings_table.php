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
        Schema::create('driver_ratings', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('delivery_id');
            $table->uuid('driver_id');
            $table->uuid('customer_id');

            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->foreign('delivery_id')->references('id')->on('deliveries')->cascadeOnDelete();
            $table->foreign('driver_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('customer_id')->references('id')->on('users')->cascadeOnDelete();

            $table->unique(['delivery_id', 'customer_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_ratings');
    }
};
