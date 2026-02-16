<?php
namespace App\Mail;

use Illuminate\Mail\Mailable;

class WalletDebitedMail extends Mailable
{
    public $user;
    public $amount;
    public $transaction;
    public $summary;

    public function __construct($user, $amount, $transaction, string $summary)
    {
        $this->user = $user;
        $this->amount = $amount;
        $this->transaction = $transaction;
        $this->summary = $summary;
    }

    public function build()
    {
        return $this->subject('Your Wallet has been Debited')
            ->view('emails.wallet_debited');
    }
}

