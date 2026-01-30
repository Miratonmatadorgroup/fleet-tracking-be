<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('api_endpoints', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('project_id');
            $table->string('title');
            $table->text('description')->nullable();

            // HTTP method (enum-backed at app level)
            $table->string('method', 10);

            // e.g /auth/register
            $table->string('path');

            // Full resolved URL
            $table->string('full_url');

            $table->string('version', 10)->default('v1');
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // Indexes & constraints
            $table->index('project_id');
            $table->index(['project_id', 'method']);
            $table->index('is_active');

            $table->foreign('project_id')
                ->references('id')
                ->on('projects')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_endpoints');
    }
};
