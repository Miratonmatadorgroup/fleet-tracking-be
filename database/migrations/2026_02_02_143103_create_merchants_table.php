<?php

use App\Enums\MerchantStatusEnums;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('merchants', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Business owner (must be a business operator user)
            $table->foreignUuid('user_id')
                ->constrained()
                ->cascadeOnDelete();

            // Public-facing identifier
            $table->string('merchant_code')->unique(); // e.g. MERCHANT_ABC123

            // Status stored as STRING (Enum enforced in PHP)
            $table->string('status')
                ->default(MerchantStatusEnums::PENDING->value)
                ->index();

            /**
             * Verification / approval tracking
             * (Smile ID CAC verification etc.)
             */
            $table->timestamp('verified_at')->nullable();
            $table->foreignUuid('verified_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();

            /**
             * Soft control (without soft deletes)
             */
            $table->timestamp('suspended_at')->nullable();
            $table->text('suspension_reason')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('merchants');
    }
};
