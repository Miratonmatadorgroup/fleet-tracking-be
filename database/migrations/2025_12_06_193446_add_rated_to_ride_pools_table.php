<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ride_pools', function (Blueprint $table) {
            $table->boolean('rated')
                ->nullable()          
                ->default(null);
        });
    }

    public function down(): void
    {
        Schema::table('ride_pools', function (Blueprint $table) {
            $table->dropColumn('rated');
        });
    }
};
