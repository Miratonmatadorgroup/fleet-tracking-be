<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;


class ElectricityPurchaseReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public $user,
        public float $amount,
        public string $meter,
        public string $disco,
        public string $reference,
        public ?string $units,
        public ?string $token
    ) {}


    public function build()
    {
        return $this->subject('Electricity Purchase Receipt')
            ->view('emails.bills_payment.electricity-receipt')
            ->with([
                'user'      => $this->user,
                'amount'    => $this->amount,
                'meter'     => $this->meter,
                'disco'     => $this->disco,
                'reference' => $this->reference,
                'units'     => $this->units,
                'token'     => $this->token,
            ]);
    }
}
