<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->string('external_account_number')->nullable();
            $table->string('external_account_name')->nullable();
            $table->string('external_bank')->nullable();
            $table->string('external_reference')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->dropColumn([
                'external_account_number',
                'external_account_name',
                'external_bank',
                'external_reference',
            ]);
        });
    }
};
