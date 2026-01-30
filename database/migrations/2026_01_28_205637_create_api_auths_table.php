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
        Schema::create('api_auths', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('api_endpoint_id');

            // none | bearer | api_key | basic (enum enforced at app level)
            $table->string('type', 20);

            $table->text('description')->nullable();

            $table->timestamps();

            // Indexes & constraints
            $table->index('api_endpoint_id');
            $table->index(['api_endpoint_id', 'type']);

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
        Schema::dropIfExists('api_auths');
    }
};
