<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class PartnerApplicationReceived extends Mailable
{
    public $partner;

    public function __construct($partner)
    {
        $this->partner = $partner;
    }

    public function build()
    {
        return $this->subject('Your Partner Application Has Been Received')
            ->view('emails.partner.application_received')
            ->with([
                'partner' => $this->partner,
            ]);
    }
}
