<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class UpdateUserEmailsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // FOR LOCAL
        // $usersToUpdate = [
        //     [
        //         'id' => 'f93cb646-2586-4636-8db8-99f5c094a123',
        //         'new_email' => 'admin@shanonobank.com',
        //     ],
        //     [
        //         'id' => '2b548126-dff2-4a89-955a-fc26026d0d87',
        //         'new_email' => 'tnt@system.com',
        //     ],
        // ];
        // FOR STAGING
        $usersToUpdate = [
            // FOR SHANONO BANK
            [
                'id' => 'd0b7b3a2-d74a-40ac-8888-0542d645730d',
                'new_email' => 'poskanzee@gmail.com',
            ],
            // FOR TNT DEALS
            [
                'id' => '7db4a27f-6b32-4c2d-88fe-c66b684d6f21',
                'new_email' => 'derektippy@aol.com',
            ],
            // FOR ANP
            [
                'id' => '48be34e2-2dfe-4f38-a363-c5abdb8e91f8',
                'new_email' => 'cathsroge59@aol.com',
            ],
        ];

        // FOR PRODUCTION
        // $usersToUpdate = [
        //     // FOR SHANONO BANK
        //     [
        //         'id' => '300253c0-5bd4-4f00-bb1c-0add823ccfc7',
        //         'new_email' => 'admin@myshanonobank.com',
        //     ],
        //     // FOR TNT DEALS
        //     [
        //         'id' => '14201172-7d7f-478c-93d3-1e1df8dd6016',
        //         'new_email' => 'admin@tandtdeals.ng',
        //     ],
        //     // FOR ANP
        //     [
        //         'id' => '8f7367e0-4515-40d1-99df-74c4c8880855',
        //         'new_email' => 'admin.anp@myshanonobank.com',
        //     ],
        // ];

        foreach ($usersToUpdate as $user) {
            $updated = DB::table('users')
                ->where('id', $user['id'])
                ->update([
                    'email' => $user['new_email'],
                    'updated_at' => now(),
                ]);

            if ($updated) {
                $this->command->info("Updated email for user ID {$user['id']}");
            } else {
                $this->command->warn("No user found with ID {$user['id']}");
            }
        }
    }
}
