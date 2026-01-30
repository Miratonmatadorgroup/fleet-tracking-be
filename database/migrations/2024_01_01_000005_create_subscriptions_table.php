<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary(); // use UUID for PK
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->enum('plan_class', ['A', 'B', 'C']);
            $table->enum('billing_cycle', ['monthly', 'quarterly', 'biannual', 'yearly']);
            $table->decimal('price_per_month', 10, 2);
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['active', 'expired', 'cancelled', 'suspended'])->default('active');
            $table->boolean('is_trial')->default(false);
            $table->date('trial_end_date')->nullable();
            $table->boolean('auto_renew')->default(true);
            $table->string('payment_method', 50)->nullable();
            $table->string('stripe_subscription_id')->nullable();
            $table->string('paystack_subscription_code')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('asset_id');
            $table->index('status');
            $table->index('end_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
