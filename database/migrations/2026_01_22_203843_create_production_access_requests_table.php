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
        Schema::create('production_access_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
           $table->string('app_type'); // sister | external
            $table->string('status')->default('pending');
            // CAC-related
            $table->string('cac_document_path')->nullable();
            $table->json('cac_verification_result')->nullable();

            $table->timestamp('verified_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->uuid('approved_by')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_access_requests');
    }
};
