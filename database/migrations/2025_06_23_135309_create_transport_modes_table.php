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
        Schema::create('transport_modes', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('driver_id')->nullable();
            $table->foreign('driver_id')->references('id')->on('drivers')->onDelete('cascade');

            $table->uuid('partner_id')->nullable();
            $table->foreign('partner_id')->references('id')->on('partners')->onDelete('cascade');


            // Store the enum as a string
            $table->string('type');

            $table->string('manufacturer')->nullable();
            $table->string('model')->nullable();
            $table->string('registration_number')->unique();
            $table->year('year_of_manufacture')->nullable();
            $table->string('color')->nullable();
            $table->integer('passenger_capacity')->nullable();
            $table->decimal('max_weight_capacity', 10, 2)->nullable();

            $table->string('photo_path')->nullable();
            $table->string('registration_document')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transport_modes');
    }
};
