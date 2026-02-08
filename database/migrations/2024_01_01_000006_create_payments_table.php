<?php

use App\Enums\PaymentStatusEnums;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Foreign key to subscriptions (UUID)
            $table->foreignUuid('subscription_id')
                ->nullable()
                ->constrained('subscriptions')
                ->cascadeOnDelete();
            $table->foreignUuid('delivery_id')->nullable()->constrained('deliveries')->onDelete('cascade');

            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('NGN');
            $table->string('transaction_id')->unique()->nullable();
            $table->json('gateway_response')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index('subscription_id');
            $table->index('transaction_id');

            $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade');
            $table->string('status')->default(PaymentStatusEnums::PENDING->value);
            $table->string('reference')->unique();
            $table->string('gateway')->nullable();
            $table->json('meta')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
