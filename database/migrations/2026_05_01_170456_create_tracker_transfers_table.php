<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tracker_transfers', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('tracker_id');
            $table->uuid('from_user_id')->nullable();
            $table->uuid('to_user_id');
            $table->uuid('performed_by');

            $table->timestamps();

            // Foreign keys
            $table->foreign('tracker_id')
                ->references('id')
                ->on('trackers')
                ->cascadeOnDelete();

            $table->foreign('from_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->foreign('to_user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            $table->foreign('performed_by')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracker_transfers');
    }
};
