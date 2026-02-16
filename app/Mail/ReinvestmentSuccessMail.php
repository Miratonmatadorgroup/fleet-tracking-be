<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ReinvestmentSuccessMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $amount;

    public function __construct($user, $amount)
    {
        $this->user = $user;
        $this->amount = $amount;
    }

    public function build()
    {
        return $this->subject('Reinvestment Payment Successful')
            ->view('emails.investor.reinvestment-success')
            ->with([
                'name'   => $this->user->name,
                'amount' => number_format($this->amount, 2),
            ]);
    }
}
