<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Database\Seeder;

class ExternalSystemUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            [
                'email' => 'poskanzee@gmail.com',
                'name'  => 'Shanono Bank System User',
            ],
            [
                'email' => 'externalpartner@system.com',
                'name'  => 'External Partner System User',
            ],
            [
                'email' => 'derektippy@aol.com',
                'name'  => 'TNT Partner System User',
            ],
            [
                'email' => 'cathsroge59@aol.com',
                'name'  => 'ANP Partner System User',
            ],
        ];

        foreach ($users as $userData) {
            User::updateOrCreate(
                ['email' => $userData['email']],
                [
                    'name'     => $userData['name'],
                    'password' => bcrypt('secret123'),
                ]
            );
        }
    }
}
