<?php

namespace App\Mail;

use App\Models\Payout;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Contracts\Queue\ShouldQueue;

class PayoutInitiatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Payout $payout) {}

    public function build()
    {
        return $this->view('emails.payout.initiated')
            ->subject('Your Payout Request')
            ->with([
                'payout' => $this->payout,
            ]);
    }
}
