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
        Schema::table('deliveries', function (Blueprint $table) {
            $table->decimal('base_price', 12, 2)->nullable()->after('total_price');
            $table->decimal('subsidized_price', 12, 2)->nullable()->after('base_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropColumn('base_price');
            $table->dropColumn('subsidized_price');
        });
    }
};
