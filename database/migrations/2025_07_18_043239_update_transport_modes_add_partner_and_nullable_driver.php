<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $dbName = DB::connection()->getDatabaseName();
        $driver = DB::getDriverName();

        // Only check CONSTRAINT_SCHEMA for MySQL
        $sql = "
            SELECT CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_NAME = 'transport_modes'
            AND COLUMN_NAME = 'partner_id'
        ";

        if ($driver === 'mysql') {
            $sql .= " AND CONSTRAINT_SCHEMA = ?";
            $bindings = [$dbName];
        } else {
            $bindings = [];
        }

        $fkName = DB::select($sql, $bindings);

        if (!empty($fkName)) {
            $fk = $fkName[0]->constraint_name ?? $fkName[0]->CONSTRAINT_NAME;

            // Drop FK differently based on driver
            if ($driver === 'pgsql') {
                DB::statement("ALTER TABLE transport_modes DROP CONSTRAINT IF EXISTS \"$fk\"");
            } else {
                DB::statement("ALTER TABLE transport_modes DROP FOREIGN KEY `$fk`");
            }
        }

        if (Schema::hasColumn('transport_modes', 'partner_id')) {
            Schema::table('transport_modes', function (Blueprint $table) {
                $table->dropColumn('partner_id');
            });
        }

        Schema::table('transport_modes', function (Blueprint $table) {
            $table->dropForeign(['driver_id']);
            $table->uuid('driver_id')->nullable()->change();
        });

        Schema::table('transport_modes', function (Blueprint $table) {
            $table->foreign('driver_id')->references('id')->on('drivers')->onDelete('cascade');
            $table->uuid('partner_id')->nullable()->after('driver_id');
            $table->foreign('partner_id')->references('id')->on('partners')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        $dbName = DB::connection()->getDatabaseName();
        $driver = DB::getDriverName();

        $sql = "
            SELECT CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_NAME = 'transport_modes'
            AND COLUMN_NAME = 'partner_id'
        ";

        if ($driver === 'mysql') {
            $sql .= " AND CONSTRAINT_SCHEMA = ?";
            $bindings = [$dbName];
        } else {
            $bindings = [];
        }

        $fkName = DB::select($sql, $bindings);

        if (!empty($fkName)) {
            $fk = $fkName[0]->constraint_name ?? $fkName[0]->CONSTRAINT_NAME;

            if ($driver === 'pgsql') {
                DB::statement("ALTER TABLE transport_modes DROP CONSTRAINT IF EXISTS \"$fk\"");
            } else {
                DB::statement("ALTER TABLE transport_modes DROP FOREIGN KEY `$fk`");
            }
        }

        if (Schema::hasColumn('transport_modes', 'partner_id')) {
            Schema::table('transport_modes', function (Blueprint $table) {
                $table->dropColumn('partner_id');
            });
        }

        Schema::table('transport_modes', function (Blueprint $table) {
            $table->dropForeign(['driver_id']);
            $table->uuid('driver_id')->change(); // make non-nullable if desired
            $table->foreign('driver_id')->references('id')->on('drivers')->onDelete('cascade');
        });
    }
};
