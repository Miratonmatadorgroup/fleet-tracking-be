<?php

namespace App\Mail;

use App\Models\Investor;
use Illuminate\Mail\Mailable;

class InvestorApplicationDecision extends Mailable
{
    public Investor $investor;
    public string $status;

    public function __construct(Investor $investor, string $action)
    {
        $this->investor = $investor;
        $this->status = $action === 'approve' ? 'approved' : 'rejected';
    }

    public function build()
    {
        return $this->subject("Your Investor Application Has Been " . ucfirst($this->status))
            ->view('emails.investor.application_decision')
            ->with([
                'investor' => $this->investor,
                'status' => $this->status,
            ]);
    }
}

