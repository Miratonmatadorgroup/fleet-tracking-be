<?php 
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ClientDebtSummaryReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public $summary;

    public function __construct(array $summary)
    {
        $this->summary = $summary;
    }

    public function build()
    {
        return $this->subject('Your Debt Summary Report')
                    ->markdown('emails.client.debt_summary')
                    ->with('summary', $this->summary);
    }
}
