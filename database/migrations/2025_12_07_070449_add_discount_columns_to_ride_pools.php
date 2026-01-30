<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ride_pools', function (Blueprint $table) {
            $table->decimal('discount_percentage', 5, 2)
                  ->nullable();

            $table->decimal('discount_cost', 10, 2)
                  ->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ride_pools', function (Blueprint $table) {
            $table->dropColumn('discount_percentage');
            $table->dropColumn('discount_cost');
        });
    }
};
