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
        Schema::table('production_access_requests', function (Blueprint $table) {
            $table->string('cac_number')->nullable();
            $table->string('business_type')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('production_access_requests', function (Blueprint $table) {
            $table->dropColumn(['cac_number', 'business_type',]);
        });
    }
};