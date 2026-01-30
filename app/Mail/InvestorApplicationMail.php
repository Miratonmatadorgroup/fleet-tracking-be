<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InvestorApplicationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $application;

    /**
     * Create a new message instance.
     *
     * @param mixed $application
     */
    public function __construct($application)
    {
        $this->application = $application;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('Thank you for your Investor Application')
                    ->view('emails.investor.application_confirmation')
                    ->with([
                        'name' => $this->application->full_name ?? $this->application->name,
                        'investmentAmount' => $this->application->amount ?? null,
                        'application' => $this->application,
                    ]);
    }
}
