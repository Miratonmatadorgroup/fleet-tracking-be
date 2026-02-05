<?php

use Illuminate\Support\Facades\Schema;
use App\Enums\NinVerificationStatusEnums;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('nin_verifications', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('user_id');
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            $table->string('job_id')->unique();
            $table->string('nin_number');

            $table->string('status')
                ->default(NinVerificationStatusEnums::PENDING->value)
                ->index();

            $table->json('result')->nullable();
                        $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nin_verifications');
    }
};
