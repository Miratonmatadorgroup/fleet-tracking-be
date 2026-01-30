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
        Schema::create('reward_campaigns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('type');
            $table->text('description')->nullable();
            $table->decimal('reward_amount', 12, 2);
            $table->boolean('active')->default(true);
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reward_campaigns');
    }
};
