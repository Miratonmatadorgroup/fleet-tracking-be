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
        Schema::create('deliveries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('receiver_name');
            $table->string('receiver_phone');
            $table->string('sender_name')->nullable();
            $table->string('sender_phone')->nullable();
            $table->string('package_type');
            $table->string('other_package_type')->nullable();
            $table->string('pickup_location');
            $table->string('dropoff_location');
            $table->string('mode_of_transportation');
            $table->string('delivery_type');
            $table->string('package_description');
            $table->float('package_weight');
            $table->timestamp('delivery_date');
            $table->time('delivery_time');
            $table->string('tracking_number')->nullable()->unique();
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('tax', 10, 2)->default(0);
            $table->decimal('total_price', 10, 2)->default(0);
            $table->integer('estimated_days');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};
