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
            if (Schema::hasColumn('deliveries', 'customer_id')) {
                $table->uuid('customer_id')->nullable()->change();
            }

            if (!Schema::hasColumn('deliveries', 'created_by')) {
                $table->uuid('created_by')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            if (Schema::hasColumn('deliveries', 'customer_id')) {
                $table->uuid('customer_id')->nullable(false)->change();
            }

            if (Schema::hasColumn('deliveries', 'created_by')) {
                $table->dropColumn('created_by');
            }
        });
    }
};
