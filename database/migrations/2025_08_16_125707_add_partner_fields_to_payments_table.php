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
        Schema::table('payments', function (Blueprint $table) {
            $table->uuid('api_client_id')->nullable()->after('user_id');
            $table->foreign('api_client_id')->references('id')->on('api_clients')->nullOnDelete();

            $table->string('currency', 10)->default('NGN')->after('amount'); // adjust length for ISO codes
            $table->string('callback_url')->nullable()->after('gateway');
            $table->json('meta')->nullable()->after('callback_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['api_client_id']);
            $table->dropColumn(['api_client_id', 'currency', 'callback_url', 'meta']);
        });
    }
};
