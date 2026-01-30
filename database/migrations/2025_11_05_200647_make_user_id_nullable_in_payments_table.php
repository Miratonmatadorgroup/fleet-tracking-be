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
        if (Schema::hasColumn('payments', 'user_id')) {
            Schema::table('payments', function (Blueprint $table) {
                // Make the user_id column nullable
                $table->uuid('user_id')->nullable()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
       if (Schema::hasColumn('payments', 'user_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->uuid('user_id')->nullable(false)->change();
            });
        }
    
    }
};
