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
        Schema::table('transport_modes', function (Blueprint $table) {
            $table->string('category')->nullable()->after('type');
        });

        DB::table('transport_modes')->update(['category' => 'cargo']);

        DB::statement('ALTER TABLE transport_modes ALTER COLUMN category SET NOT NULL');
    }

    public function down(): void
    {
        Schema::table('transport_modes', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
