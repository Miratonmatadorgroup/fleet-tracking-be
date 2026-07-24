<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->string('asset_type')->change();
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->enum('asset_type', [
                'car',
                'bike',
                'suv',
                'truck',
                'van',
                'boat',
                'helicopter',
                'plane',
                'ship',
            ])->change();
        });
    }
};
