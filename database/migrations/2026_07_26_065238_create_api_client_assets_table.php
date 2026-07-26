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
        Schema::create('api_client_assets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('api_client_id')->constrained('api_clients')->cascadeOnDelete();
            $table->foreignUuid('asset_id')->nullable()->constrained('assets')->nullOnDelete();
            $table->foreignUuid('tracker_id')->nullable()->constrained('trackers')->nullOnDelete();
            $table->string('imei');
            $table->timestamps();
            $table->unique(['api_client_id', 'imei']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_client_assets');
    }
};
