<?php

namespace App\Console\Commands;

use App\Models\ApiClient;
use Illuminate\Console\Command;
use App\Services\ClientDebtService;
use Illuminate\Support\Facades\Log;

class CalculateExternalClientDebtSummary extends Command
{
    protected $signature = 'report:calculate-client-debt-summary';
    protected $description = 'Only calculate debt summaries for clients';

    protected $debtService;

    public function __construct(ClientDebtService $debtService)
    {
        parent::__construct();
        $this->debtService = $debtService;
    }

    public function handle()
    {
        $clients = ApiClient::where('active', 1)->get();

        foreach ($clients as $client) {
            try {
                $this->debtService->calculateDebtSummary($client);
                $this->info("Debt summary calculated for {$client->name}");
            } catch (\Throwable $th) {
                Log::error("Failed to calculate debt summary for client ID {$client->id}: {$th->getMessage()}");
            }
        }
    }
}
