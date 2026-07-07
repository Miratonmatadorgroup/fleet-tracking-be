<?php

use App\Enums\GeoFenceActionTypeEnums;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('geofences', function (Blueprint $table) {

            $table->string('action')->default(GeoFenceActionTypeEnums::NONE->value);
        });
    }

    public function down(): void
    {
        Schema::table('geofences', function (Blueprint $table) {

            $table->dropColumn('action');
        });
    }
};
