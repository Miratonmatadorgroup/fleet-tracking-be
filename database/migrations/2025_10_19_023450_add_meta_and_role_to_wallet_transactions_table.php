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
        Schema::table('wallet_transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('wallet_transactions', 'meta')) {
                $table->json('meta')->nullable();
            }

            if (!Schema::hasColumn('wallet_transactions', 'role')) {
                $table->string('role')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('wallet_transactions', 'meta')) {
                $table->dropColumn('meta');
            }

            if (Schema::hasColumn('wallet_transactions', 'role')) {
                $table->dropColumn('role');
            }
        });
    }
};
