<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TransactionPinOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $otp;
    public string $name;

    public function __construct(string $otp, string $name)
    {
        $this->otp  = $otp;
        $this->name = $name;
    }

    public function build()
    {
        return $this->subject('Your PIN Reset OTP')
            ->view('emails.transaction-pin-otp')
            ->with([
                'otp'  => $this->otp,
                'name' => $this->name,
            ]);
    }
}
