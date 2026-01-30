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
        Schema::create('api_clients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name'); 
            $table->string('api_key')->unique();
            $table->enum('environment', ['sandbox', 'production'])->index();
            $table->boolean('active')->default(true)->index();
            $table->json('ip_whitelist')->nullable(); // optional security
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_clients');
    }
};
