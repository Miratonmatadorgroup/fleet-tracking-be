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
        Schema::create('api_responses', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('api_endpoint_id');

            $table->unsignedSmallInteger('status_code');
            $table->string('description')->nullable();

            // Response body schema / example
            $table->json('body')->nullable();

            $table->timestamps();

            // Indexes & constraints
            $table->index('api_endpoint_id');
            $table->index(['api_endpoint_id', 'status_code']);

            $table->foreign('api_endpoint_id')
                ->references('id')
                ->on('api_endpoints')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_responses');
    }
};
