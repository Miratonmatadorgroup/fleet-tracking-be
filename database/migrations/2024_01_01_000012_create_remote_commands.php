<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('remote_commands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('command_type', ['shutdown', 'restart', 'unlock']);
            $table->enum('status', ['pending', 'sent', 'acknowledged', 'failed'])->default('pending');
            $table->json('api_response')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();

            $table->index('asset_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remote_commands');
    }
};