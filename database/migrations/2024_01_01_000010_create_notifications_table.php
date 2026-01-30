<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Use UUID for user_id
            $table->uuid('user_id');

            // Foreign key constraint
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            // 'geofence_breach', 'speeding', 'idle_alert', 'subscription_expiry', 'remote_shutdown'
            $table->string('type');


            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable();
            $table->boolean('is_read')->default(false);
            $table->enum('sent_via', ['email', 'sms', 'push', 'in_app'])->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('is_read');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
