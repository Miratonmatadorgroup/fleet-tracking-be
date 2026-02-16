<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CableTvPurchaseReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public $user,
        public float $amount,
        public string $decoder,
        public string $provider,
        public string $reference,
        public string $package,
    ) {}

    public function build()
    {
        return $this->subject('Cable Tv Subscription Receipt')
            ->view('emails.bills_payment.cabletv-receipt')
            ->with([
                'user'           => $this->user,
                'amount'         => $this->amount,
                'decoder_number' => $this->decoder,
                'provider'       => $this->provider,
                'package'        => $this->package,
                'reference'      => $this->reference,
            ]);
    }
}
