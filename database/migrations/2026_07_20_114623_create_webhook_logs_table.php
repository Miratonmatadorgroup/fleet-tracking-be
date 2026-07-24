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
        Schema::create('webhook_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('delivery_id')
                ->constrained('deliveries')
                ->cascadeOnDelete();

            $table->foreignUuid('api_client_webhook_id')
                ->nullable()
                ->constrained('api_client_webhooks')
                ->nullOnDelete();

            $table->string('event');

            $table->string('url');

            $table->integer('response_code')
                ->nullable();

            $table->json('payload');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_logs');
    }
};
