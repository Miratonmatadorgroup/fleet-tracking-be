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
        Schema::create('reward_criteria', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('reward_campaign_id');
            $table->string('metric');
            $table->string('operator');
            $table->string('value'); 
            $table->string('unit')->nullable(); // e.g., 'deliveries', 'naira', 'km'
            $table->timestamps();

            $table->foreign('reward_campaign_id')
                ->references('id')
                ->on('reward_campaigns')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reward_criteria');
    }
};
