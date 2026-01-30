<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transport_modes', function (Blueprint $table) {
            $table->boolean('is_flagged')->default(false);
            $table->string('flag_reason')->nullable();
            $table->uuid('flagged_by')->nullable();

            $table->foreign('flagged_by')
                  ->references('id')
                  ->on('users')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transport_modes', function (Blueprint $table) {
            $table->dropForeign(['flagged_by']);
            $table->dropColumn(['is_flagged', 'flag_reason', 'flagged_by']);
        });
    }
};

