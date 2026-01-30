<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected string $fkName = 'driver_ratings_ride_pool_id_foreign';

    public function up(): void
    {
        Schema::table('driver_ratings', function (Blueprint $table) {
            if (!Schema::hasColumn('driver_ratings', 'ride_pool_id')) {

                $table->uuid('ride_pool_id')->nullable()->after('delivery_id');

                $table->foreign('ride_pool_id', $this->fkName)
                    ->references('id')
                    ->on('ride_pools')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('driver_ratings', 'ride_pool_id')) {
            Schema::table('driver_ratings', function (Blueprint $table) {

                $table->dropForeign($this->fkName);

                $table->dropColumn('ride_pool_id');
            });
        }
    }
};

