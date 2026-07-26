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
        Schema::table('api_clients', function (Blueprint $table) {
            $table->string('company_name')->after('name');
            $table->string('company_website')->nullable()->after('company_name');
            $table->string('callback_url')->nullable()->after('company_website');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('api_clients', function (Blueprint $table) {
            $table->dropColumn([
                'company_name',
                'company_website',
                'callback_url',
            ]);
        });
    }
};