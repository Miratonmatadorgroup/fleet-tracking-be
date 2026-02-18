<?php

namespace App\Console;

use App\Jobs\RetryDriverAssignmentJob;
use Illuminate\Console\Scheduling\Schedule;

use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->job(job: RetryDriverAssignmentJob::class)->everyFiveMinutes();

        $schedule->command('report:calculate-client-debt-summary')->everyTenMinutes();
        $schedule->command('report:send-client-debt-summary')->dailyAt('06:00');
        $schedule->command('report:send-client-debt-summary')->weeklyOn(1, '06:00');
        $schedule->command('commissions:release-pending')->hourly();
        $schedule->command('bills:resolve-pending')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
        $schedule->command('subscriptions:process-expired')->daily();
    }
    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
