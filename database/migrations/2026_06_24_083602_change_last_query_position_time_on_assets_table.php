<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add a new nullable timestamp column
        Schema::table('assets', function (Blueprint $table) {
            $table->timestamp('last_query_position_time_new')->nullable();
        });

        // 2. Copy/convert old values into the new column
        DB::statement("
            UPDATE assets
            SET last_query_position_time_new =
                CASE
                    WHEN last_query_position_time IS NULL OR last_query_position_time = 0 THEN NULL
                    ELSE FROM_UNIXTIME(last_query_position_time)
                END
        ");

        // 3. Drop the old bigint column
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn('last_query_position_time');
        });

        // 4. Rename the new timestamp column to the original column name
        Schema::table('assets', function (Blueprint $table) {
            $table->renameColumn('last_query_position_time_new', 'last_query_position_time');
        });
    }

    public function down(): void
    {
        // 1. Add back the old bigint column
        Schema::table('assets', function (Blueprint $table) {
            $table->bigInteger('last_query_position_time_old')->default(0);
        });

        // 2. Convert timestamp values back to unix timestamps
        DB::statement("
            UPDATE assets
            SET last_query_position_time_old =
                CASE
                    WHEN last_query_position_time IS NULL THEN 0
                    ELSE UNIX_TIMESTAMP(last_query_position_time)
                END
        ");

        // 3. Drop the timestamp column
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn('last_query_position_time');
        });

        // 4. Rename old bigint column back
        Schema::table('assets', function (Blueprint $table) {
            $table->renameColumn('last_query_position_time_old', 'last_query_position_time');
        });
    }
};
