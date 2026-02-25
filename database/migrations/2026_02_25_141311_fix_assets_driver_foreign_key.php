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
        Schema::table('assets', function (Blueprint $table) {
            $table->dropForeign(['driver_id']);

            $table->foreign('driver_id')
                ->references('id')
                ->on('drivers')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropForeign(['driver_id']);

            $table->foreign('driver_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }
};
