<?php

namespace App\Console\Commands;

use App\Models\ApiClient;
use Illuminate\Console\Command;
use App\Services\ClientDebtService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\ClientDebtSummaryReportMail;

class SendExternalClientDebtSummaryEmails extends Command
{
    protected $signature = 'report:send-client-debt-summary';
    protected $description = 'Send client debt summary reports via email';

    protected $debtService;

    public function __construct(ClientDebtService $debtService)
    {
        parent::__construct();
        $this->debtService = $debtService;
    }

    public function handle()
    {
        $clients = ApiClient::where('active', true)->get();

        foreach ($clients as $client) {
            try {
                $summary = $this->debtService->calculateDebtSummary($client);

                if ($summary['email']) {
                    Mail::to($summary['email'])->send(new ClientDebtSummaryReportMail($summary));
                    $this->info("Debt summary sent to {$summary['email']}");
                }
            } catch (\Throwable $th) {
                Log::error("Failed to send debt summary for client ID {$client->id}: {$th->getMessage()}");
            }
        }
    }
}
