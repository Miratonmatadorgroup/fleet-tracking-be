<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class BackfillUserBankDetailsSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {

            $now = Carbon::now();

            /**
             * =====================
             * DRIVERS → USERS
             * =====================
             */
            DB::table('drivers')
                ->whereNotNull('user_id')
                ->whereNotNull('bank_name')
                ->chunkById(200, function ($drivers) use ($now) {
                    foreach ($drivers as $driver) {
                        DB::table('users')
                            ->where('id', $driver->user_id)
                            ->whereNull('bank_name') // prevent overwrite
                            ->update([
                                'bank_name'               => $driver->bank_name,
                                'account_name'            => $driver->account_name,
                                'account_number'          => $driver->account_number,
                                'bank_code'               => $driver->bank_code ?? null,
                                'bank_details_updated_at' => $now,
                                'updated_at'              => $now,
                            ]);
                    }
                });

            /**
             * =====================
             * INVESTORS → USERS
             * =====================
             */
            DB::table('investors')
                ->whereNotNull('user_id')
                ->whereNotNull('bank_name')
                ->chunkById(200, function ($investors) use ($now) {
                    foreach ($investors as $investor) {
                        DB::table('users')
                            ->where('id', $investor->user_id)
                            ->whereNull('bank_name')
                            ->update([
                                'bank_name'               => $investor->bank_name,
                                'account_name'            => $investor->account_name,
                                'account_number'          => $investor->account_number,
                                'bank_code'               => $investor->bank_code ?? null,
                                'bank_details_updated_at' => $now,
                                'updated_at'              => $now,
                            ]);
                    }
                });

            /**
             * =====================
             * PARTNERS → USERS
             * =====================
             */
            DB::table('partners')
                ->whereNotNull('user_id')
                ->whereNotNull('bank_name')
                ->chunkById(200, function ($partners) use ($now) {
                    foreach ($partners as $partner) {
                        DB::table('users')
                            ->where('id', $partner->user_id)
                            ->whereNull('bank_name')
                            ->update([
                                'bank_name'               => $partner->bank_name,
                                'account_name'            => $partner->account_name,
                                'account_number'          => $partner->account_number,
                                'bank_code'               => $partner->bank_code ?? null,
                                'bank_details_updated_at' => $now,
                                'updated_at'              => $now,
                            ]);
                    }
                });

        });
    }
}
