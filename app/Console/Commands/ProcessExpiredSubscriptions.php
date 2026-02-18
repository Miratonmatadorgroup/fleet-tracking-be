<?php

namespace App\Console\Commands;

use App\AutoRenewSubscriptionAction;
use App\Enums\SubscriptionStatusEnums;
use App\Models\Subscription;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProcessExpiredSubscriptions extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'subscriptions:process-expired';

    /**
     * The console command description.
     */
    protected $description = 'Process expired subscriptions, mark them expired and optionally auto-renew';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Processing expired subscriptions...');

        Subscription::query()
            ->where('end_date', '<=', now())
            ->where('status', SubscriptionStatusEnums::ACTIVE)
            ->chunkById(100, function ($subscriptions) {

                foreach ($subscriptions as $subscription) {

                    DB::transaction(function () use ($subscription) {

                        $subscription->update([
                            'status' => SubscriptionStatusEnums::EXPIRED,
                        ]);

                        $user = $subscription->user;

                        if ($subscription->auto_renew) {

                            $renewed = app(AutoRenewSubscriptionAction::class)
                                ->execute($subscription);

                            if (!$renewed) {
                                app(NotificationService::class)
                                    ->sendSubscriptionExpired($user, $subscription);
                            }
                        } else {

                            app(NotificationService::class)
                                ->sendSubscriptionExpired($user, $subscription);
                        }
                    });
                }
            });

        $this->info('Expired subscriptions processed.');

        return Command::SUCCESS;
    }
}
