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
        Schema::table('investors', function (Blueprint $table) {
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->text('refund_note')->nullable();
            $table->string('withdraw_status', 50)->default('none');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('investors', function (Blueprint $table) {
            $table->dropColumn(['withdrawn_at', 'refunded_at', 'refund_note', 'withdraw_status']);
        });
    }
};
