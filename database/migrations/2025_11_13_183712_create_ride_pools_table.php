<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        Schema::create('ride_pools', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('user_id');
            $table->uuid('driver_id')->nullable();
            $table->uuid('transport_mode_id');
            $table->uuid('partner_id')->nullable();

            $table->json('pickup_location');
            $table->json('dropoff_location')->nullable();

            $table->dateTime('ride_date');
            $table->dateTime('start_time')->nullable();
            $table->dateTime('end_time')->nullable();
            $table->float('duration')->nullable();
            $table->float('estimated_cost')->nullable();
            $table->integer('eta_minutes')->nullable();
            $table->timestamp('eta_timestamp')->nullable();

            $table->string('ride_pool_category')->nullable();
            $table->dateTime('driver_accepted_at')->nullable();

            $table->string('status')->default('PENDING');
            $table->string('payment_status')->default('UNPAID');
            $table->timestamps();
        });

        // Add GENERATED hash columns
        if ($driver === 'mysql') {
            DB::statement("
                ALTER TABLE ride_pools
                ADD COLUMN pickup_hash VARCHAR(32)
                GENERATED ALWAYS AS (MD5(JSON_UNQUOTE(JSON_EXTRACT(pickup_location, '$.address'))))
                STORED
            ");

            DB::statement("
                ALTER TABLE ride_pools
                ADD COLUMN dropoff_hash VARCHAR(32)
                GENERATED ALWAYS AS (MD5(JSON_UNQUOTE(JSON_EXTRACT(dropoff_location, '$.address'))))
                STORED
            ");
        } else { // PostgreSQL
            DB::statement("
                ALTER TABLE ride_pools
                ADD COLUMN pickup_hash VARCHAR(32)
                GENERATED ALWAYS AS (md5(COALESCE(pickup_location->>'address', ''))) STORED
            ");

            DB::statement("
                ALTER TABLE ride_pools
                ADD COLUMN dropoff_hash VARCHAR(32)
                GENERATED ALWAYS AS (md5(COALESCE(dropoff_location->>'address', ''))) STORED
            ");
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('ride_pools');
    }
};
