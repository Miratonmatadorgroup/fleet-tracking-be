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
        Schema::create('api_requests', function (Blueprint $table) {
            $table->id();

            $table->uuid('api_endpoint_id');

            // body | query | path (enum enforced at app level)
            $table->string('type', 20);

            // e.g application/json, multipart/form-data
            $table->string('content_type')->nullable();

            // Request schema definition
            $table->json('schema')->nullable();

            $table->timestamps();

            $table->index('api_endpoint_id');
            $table->index('type');

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
        Schema::dropIfExists('api_requests');
    }
};
