<?php
namespace App\Mail;
use Illuminate\Mail\Mailable;

class PartnerLinkDriverToTransMode extends Mailable
{
    public $driver;

    public function __construct($driver)
    {
        $this->driver = $driver;
    }

    public function build()
    {
        return $this->subject('A New Partner Has Linked You')
            ->view('emails.partner.partner_link_driver');
    }
}

