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
        Schema::table('users', function (Blueprint $table) {
            $table->string('transaction_pin')->nullable();
            $table->timestamp('pin_reset_otp_expires_at')->nullable();
            $table->string('pin_reset_otp')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'transaction_pin',
                'pin_reset_otp_expires_at',
                'pin_reset_otp',
            ]);
        });
    }
};
