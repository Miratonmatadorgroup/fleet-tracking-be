<?php

use App\Enums\TrackerStatusEnums;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('trackers', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('serial_number')->unique();
            $table->string('imei')->unique();

            // Status stored as STRING, enforced via Enum in PHP
            $table->string('status')
                ->default(TrackerStatusEnums::INACTIVE->value)
                ->index();

            $table->boolean('is_assigned')->default(false);

            /**
             * Ownership
             * A tracker can belong to:
             * - a merchant (business)
             * - OR an individual user
             */
            $table->foreignUuid('merchant_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignUuid('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            /**
             * Inventory tracking (admin action)
             */
            $table->foreignUuid('inventory_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('inventory_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trackers');
    }
};
