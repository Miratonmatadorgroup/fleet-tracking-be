<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('geofences', function (Blueprint $table) {
            // add user_id
            $table->uuid('user_id');

            // make organization_id nullable
            $table->uuid('organization_id')->nullable()->change();

            // indexes / foreign keys
            $table->index('user_id');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('geofences', function (Blueprint $table) {
            // drop FK first
            $table->dropForeign(['user_id']);
            $table->dropIndex(['user_id']);

            // drop column
            $table->dropColumn('user_id');

            // revert organization_id back to required
            $table->uuid('organization_id')->nullable(false)->change();
        });
    }
};
