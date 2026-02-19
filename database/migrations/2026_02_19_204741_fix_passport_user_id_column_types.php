<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('oauth_access_tokens', function (Blueprint $table) {
            $table->dropColumn('user_id');
        });

        Schema::table('oauth_access_tokens', function (Blueprint $table) {
            $table->uuid('user_id')->nullable()->index();
        });

        Schema::table('oauth_auth_codes', function (Blueprint $table) {
            $table->dropColumn('user_id');
        });

        Schema::table('oauth_auth_codes', function (Blueprint $table) {
            $table->uuid('user_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('oauth_access_tokens', function (Blueprint $table) {
            $table->dropColumn('user_id');
        });

        Schema::table('oauth_access_tokens', function (Blueprint $table) {
            $table->bigInteger('user_id')->nullable()->index();
        });

        Schema::table('oauth_auth_codes', function (Blueprint $table) {
            $table->dropColumn('user_id');
        });

        Schema::table('oauth_auth_codes', function (Blueprint $table) {
            $table->bigInteger('user_id')->nullable()->index();
        });
    }
};
