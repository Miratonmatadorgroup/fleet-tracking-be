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
        Schema::table('deliveries', function (Blueprint $table) {
             if (!Schema::hasColumn('deliveries', 'external_customer_ref')) {
                $table->string('external_customer_ref')->nullable()->index();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            if (Schema::hasColumn('deliveries', 'external_customer_ref')) {
                $table->dropColumn('external_customer_ref');
            }
        });
    }
};
