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
            // use uuid instead of foreignId since api_clients.id is uuid
            $table->uuid('api_client_id')->nullable()->after('id');
            $table->foreign('api_client_id')
                ->references('id')->on('api_clients')
                ->nullOnDelete();
            $table->string('customer_name')->nullable()->after('api_client_id');
            $table->string('customer_email')->nullable();
            $table->string('customer_phone')->nullable();
            $table->string('customer_whatsapp_number')->nullable();
            $table->string('external_reference')->nullable()->index(); // partner's id/reference
            // $table->string('tracking_number')->nullable()->index(); // ⚠ already exists, skip
            $table->string('source_channel')->nullable(); // e.g., "partner_api"
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropForeign(['api_client_id']);
            $table->dropColumn('api_client_id');
            
            $table->dropColumn([
                'customer_name',
                'customer_email',
                'customer_phone',
                'customer_whatsapp_number',
                'external_reference',
                'source_channel'
            ]);
        });
    }
};
