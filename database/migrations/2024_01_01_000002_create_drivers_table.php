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

        Schema::create('drivers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->string('name');
            $table->string('next_of_kin_name')->nullable();
            $table->string('next_of_kin_phone')->nullable();
            $table->string('gender')->nullable();
            $table->string('email')->unique()->nullable();
            $table->string('phone')->unique()->nullable();
            $table->string('whatsapp_number')->unique()->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('years_of_experience')->nullable();

            $table->string('address')->nullable();

            $table->string('status')->default('inactice');
            $table->string('application_status')->default('review');

            $table->string('transport_mode');


            $table->string('driver_license_number')->nullable();
            $table->date('license_expiry_date')->nullable();
            $table->string('license_image_path')->nullable();


            $table->string('national_id_number')->nullable();
            $table->string('national_id_image_path')->nullable();

            $table->string('profile_photo')->nullable();


            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->string('bank_name')->nullable();
            $table->string('account_name')->nullable();
            $table->string('account_number')->nullable();

            $table->timestamps();
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('drivers');
    }
};
