<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('delivery_assignment_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('delivery_id');
            $table->uuid('driver_id')->nullable();
            $table->string('status');
            $table->timestamp('attempted_at')->useCurrent(); 
            $table->timestamps();

            $table->foreign('delivery_id')->references('id')->on('deliveries')->onDelete('cascade');
            $table->foreign('driver_id')->references('id')->on('drivers')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_assignment_logs');
    }
};
