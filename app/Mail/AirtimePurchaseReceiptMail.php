<?php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AirtimePurchaseReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public $user,
        public float $amount,
        public string $phone,
        public string $provider,
        public string $reference
    ) {}

    public function build()
    {
        return $this->subject('Airtime Purchase Receipt')
            ->view('emails.bills_payment.airtime-receipt')
            ->with([
                'user'      => $this->user,
                'amount'    => $this->amount,
                'phone'     => $this->phone,
                'provider'  => $this->provider,
                'reference' => $this->reference,
            ]);
    }
}
