<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->uuid('external_account_id')->nullable();
            $table->decimal('external_available_balance', 15, 2)->default(0);
            $table->decimal('external_book_balance', 15, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->dropColumn([
                'external_account_id',
                'external_available_balance',
                'external_book_balance',
            ]);
        });
    }
};
