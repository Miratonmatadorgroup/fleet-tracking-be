<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ExternalBankService;

class ConfigureShanonoWebhook extends Command
{
    protected $signature = 'shanono:webhook:configure {--target=staging}';
    protected $description = 'Configure Shanono settlement webhook';

    public function handle(ExternalBankService $bank)
    {
        $target = $this->option('target');

        if (! in_array($target, ['staging', 'production'])) {
            $this->error('Invalid target. Use staging or production.');
            return;
        }

        $url = match ($target) {
            'staging' => config('services.shanono_bank.app_staging_url'),
            'production' => config('services.shanono_bank.app_production_url'),
        } . '/webhooks/shanono/settlement';

        $secret = match ($target) {
            'staging' => config('services.shanono_bank.webhook_secret_staging'),
            'production' => config('services.shanono_bank.webhook_secret_production'),
        };

         if (! $secret) {
            $this->error("Webhook secret not set for {$target}");
            return;
        }

        // Extra safety for prod
        if ($target === 'production' && ! $this->confirm(
            'You are configuring the PRODUCTION webhook. Continue?'
        )) {
            return;
        }

        $bank->configureWebhook($url, $secret);

        $this->info("Shanono webhook configured for {$target}");
    }
}


