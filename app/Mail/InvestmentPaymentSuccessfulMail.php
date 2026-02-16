<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\Investor;

class InvestmentPaymentSuccessfulMail extends Mailable
{
    use Queueable, SerializesModels;

    public Investor $application;

    /**
     * Create a new message instance.
     */
    public function __construct(Investor $application)
    {
        $this->application = $application;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Investment Payment Successful')
            ->view('emails.investor.investor_payment_success')
            ->with([
                'name' => $this->application->full_name,
                'amount' => (float) ($this->application->investment_amount ?? 0),
            ]);
    }
}
