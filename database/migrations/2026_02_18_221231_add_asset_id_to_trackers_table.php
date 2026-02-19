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
        Schema::table('trackers', function (Blueprint $table) {
            $table->uuid('asset_id')
                ->nullable();

            $table->foreign('asset_id')
                ->references('id')
                ->on('assets')
                ->nullOnDelete();

            $table->index('asset_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trackers', function (Blueprint $table) {
            $table->dropForeign(['asset_id']);
            $table->dropIndex(['asset_id']);
            $table->dropColumn('asset_id');
        });
    }
};
