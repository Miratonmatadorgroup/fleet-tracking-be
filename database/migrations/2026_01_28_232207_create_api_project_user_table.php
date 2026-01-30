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
        Schema::create('api_project_user', function (Blueprint $table) {
            $table->id();
            $table->uuid('project_id');
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();

            $table->uuid('user_id');
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['project_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_project_user');
    }
};
