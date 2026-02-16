<?php

namespace Database\Seeders;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Driver;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;


class StagingUserDriverSeeder extends Seeder
{
    public function run(): void
    {

        $walletService = app(\App\Services\WalletService::class);
        // ----------------------------------------------------
        // 1. CREATE 10 NORMAL USERS (IDEMPOTENT SAFE)
        // ----------------------------------------------------
        for ($i = 1; $i <= 10; $i++) {
            $user = User::firstOrCreate(
                ['email' => "user{$i}@example.com"],
                [
                    'name' => "User $i",
                    'phone' => "+23481000000{$i}",
                    'whatsapp_number' => "+23481000000{$i}",
                    'email_verified_at' => now(),
                    'phone_verified_at' => now(),
                    'password' => Hash::make('Password123!'),
                    'transaction_pin' => Hash::make('1111'),
                ]
            );

            // Ensure wallet exists
            if (!$user->wallet) {
                $walletService->createForUser($user->id);
            }
        }


        // ----------------------------------------------------
        // 2. TRANSPORT MODES (10 DRIVERS)
        // ----------------------------------------------------
        $transportPlan = [
            'bike',
            'bike',           // 2
            'truck',                  // 1
            'van',
            'van',             // 2
            'car',
            'car',
            'car',      // 3
            'suv',
            'suv'              // 2
        ];

        // Provided 10 driver emails
        $driverEmails = [
            "mathewapollo968@gmail.com", //
            "m.athewapollo968@gmail.com", //
            "mathew.apollo968@gmail.com", //
            "mathewa.pollo968@gmail.com", //
            "mathewapollo.968@gmail.com",//
            "matthewapollo968@gmail.com",//
            "mathewap.ollo968@gmail.com", //
            "mathewapollo9.68@gmail.com", //
            "mathewapollo96.8@gmail.com", //
            "mat.hewapollo968@gmail.com",
        ];

        // Ensure 10 drivers
        while (count($driverEmails) < 10) {
            $driverEmails[] = "driver" . count($driverEmails) . "@example.com";
        }

        $phoneStart = 8105000000;

        // ----------------------------------------------------
        // 3. CREATE DRIVERS + THEIR USERS (SAFE)
        // ----------------------------------------------------
        foreach ($transportPlan as $index => $mode) {

            $email = $driverEmails[$index];
            $phone = "+234" . ($phoneStart + $index);

            // Create USER using firstOrCreate (prevents duplicate email crash)
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => "Driver User " . ($index + 1),
                    'phone' => $phone,
                    'whatsapp_number' => $phone,
                    'email_verified_at' => now(),
                    'phone_verified_at' => now(),
                    'password' => Hash::make('Password123!'),
                    'transaction_pin' => Hash::make('1111'),
                ]
            );

             if (!$user->wallet) {
                $walletService->createForUser($user->id);
            }


            // Attach or create DRIVER profile
            Driver::updateOrCreate(
                ['user_id' => $user->id], // unique link to user
                [
                    'name' => "Driver " . ($index + 1),
                    'email' => $email,
                    'phone' => $phone,
                    'gender' => 'male',
                    'date_of_birth' => '1990-01-01',
                    'whatsapp_number' => $phone,
                    'address' => "No " . ($index + 1) . " Driver Street, Lagos",
                    'status' => 'available',
                    'application_status' => 'approved',
                    'transport_mode' => $mode,
                    'bank_name' => 'GTBank',
                    'account_name' => "Driver " . ($index + 1),
                    'account_number' => "00000" . rand(10000, 99999),
                    'years_of_experience' => rand(1, 10),
                    'next_of_kin_name' => "Next of Kin " . ($index + 1),
                    'next_of_kin_phone' => "+2348" . rand(100000000, 999999999),
                    'driver_license_number' => "DLN" . rand(100000, 999999),
                    'license_expiry_date' => Carbon::now()->addYears(3),
                    'license_image_path' => 'placeholders/driverlic.jpeg',
                    'national_id_number' => "NIN" . rand(10000000000, 99999999999),
                    'national_id_image_path' => 'placeholders/nin.jpeg',
                    'profile_photo' => 'placeholders/avatar-2.jpg',
                    'latitude' => 6.5244,
                    'longitude' => 3.3792,
                ]
            );
        }
    }
}
