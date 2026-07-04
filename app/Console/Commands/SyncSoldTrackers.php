<?php

namespace App\Console\Commands;

use App\Enums\TrackerStatusEnums;
use App\Models\Tracker;
use Illuminate\Console\Command;

class SyncSoldTrackers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Run:
     * php artisan trackers:sync-sold
     */
    protected $signature = 'trackers:sync-sold';

    /**
     * The console command description.
     */
    protected $description = 'Mark all active trackers as sold';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $updated = Tracker::query()
            ->where('status', TrackerStatusEnums::ACTIVE)
            ->whereNotNull('user_id')
            ->whereNotNull('asset_id')
            ->where('is_sold', false)
            ->update([
                'is_sold' => true,
            ]);

        $this->info("Successfully updated {$updated} tracker(s).");

        return self::SUCCESS;
    }
}
