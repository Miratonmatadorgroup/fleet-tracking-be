<?php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;


class DataPurchaseReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public $user,
        public float $amount,
        public string $phone,
        public string $provider,
        public string $reference,
        public string $units
    ) {}

    public function build()
    {
        return $this->subject('Data Purchase Receipt')
            ->view('emails.bills_payment.data-receipt')
            ->with([
                'user'      => $this->user,
                'amount'    => $this->amount,
                'phone'     => $this->phone,
                'provider'  => $this->provider,
                'reference' => $this->reference,
                'units'     => $this->units, 
            ]);
    }
}

