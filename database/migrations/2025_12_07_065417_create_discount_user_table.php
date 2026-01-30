<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discount_user', function (Blueprint $table) {

            $table->uuid('discount_id');
            $table->uuid('user_id');

            $table->timestamps();

            // Composite primary key
            $table->primary(['discount_id', 'user_id']);

            // Indexes
            $table->index('discount_id');
            $table->index('user_id');

            // Foreign Keys
            $table->foreign('discount_id')
                ->references('id')->on('discounts')
                ->onDelete('cascade');

            $table->foreign('user_id')
                ->references('id')->on('users')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_user');
    }
};
