<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignUuid('plan_id')
                ->constrained('subscription_plans');

            $table->foreignUuid('asset_id')
                ->nullable()
                ->constrained('assets')
                ->nullOnDelete();

            $table->date('start_date');
            $table->date('end_date');

            $table->string('status')->default('active');

            $table->boolean('auto_renew')->default(true);
            $table->boolean('is_trial')->default(false);

            $table->date('trial_end_date')->nullable();
            $table->string('payment_method', 50)->nullable();

            $table->timestamps();

            // Optional but smart
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
