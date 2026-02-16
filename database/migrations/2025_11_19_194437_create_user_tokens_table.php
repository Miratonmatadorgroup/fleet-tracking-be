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
        Schema::create('user_tokens', function (Blueprint $table) {
            // Primary key as UUID
            $table->uuid('id')->primary();

            // Foreign key to users table
            $table->foreignUuid('user_id')
                ->constrained()
                ->cascadeOnDelete();

            // Device + request metadata
            $table->string('device_name')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();

            // Activity tracking
            $table->timestamp('last_activity')->nullable();
            $table->timestamp('expires_at')->nullable();

            // created_at / updated_at
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_tokens');
    }
};
