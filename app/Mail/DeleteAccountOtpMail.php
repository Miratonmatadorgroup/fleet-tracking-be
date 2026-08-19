<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DeleteAccountOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $otp;
    public string $name;

    public function __construct(string $otp, string $name)
    {
        $this->otp = $otp;
        $this->name = $name;
    }

    public function build()
    {
        return $this->subject('Account Deletion Verification Code')
            ->view('emails.delete-account-otp')
            ->with([
                'otp'  => $this->otp,
                'name' => $this->name,
            ]);
    }
}
