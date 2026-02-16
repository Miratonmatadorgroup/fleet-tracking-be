<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PartnerApplicationStatus extends Mailable
{
    use Queueable, SerializesModels;

    public $partner;
    public $approved;

    public function __construct($partner, $approved)
    {
        $this->partner = $partner;
        $this->approved = $approved;
    }

    public function build()
    {
        return $this->subject('Your Partner Application has been ' . ($this->approved ? 'Approved' : 'Rejected'))
                    ->view('emails.partner.partner_application_status')
                    ->with([
                        'partner' => $this->partner,
                        'approved' => $this->approved,
                    ]);
    }
}
