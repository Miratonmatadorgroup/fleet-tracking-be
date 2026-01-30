<?php

use Illuminate\Support\Facades\DB;
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
        Schema::table('deliveries', function (Blueprint $table) {
            $table->string('waybill_number', 20)->nullable()->after('tracking_number');
        });

        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            // PostgreSQL: create partial index to allow multiple NULLs
            DB::statement('CREATE UNIQUE INDEX deliveries_waybill_number_unique ON deliveries (waybill_number) WHERE waybill_number IS NOT NULL;');
        } elseif ($driver === 'mysql') {
            // MySQL: create a normal unique index
            Schema::table('deliveries', function (Blueprint $table) {
                $table->unique('waybill_number', 'deliveries_waybill_number_unique');
            });
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS deliveries_waybill_number_unique;');
        } elseif ($driver === 'mysql') {
            Schema::table('deliveries', function (Blueprint $table) {
                $table->dropUnique('deliveries_waybill_number_unique');
            });
        }

        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropColumn('waybill_number');
        });
    }
};
