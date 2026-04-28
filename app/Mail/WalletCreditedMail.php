<?php
namespace App\Mail;

use Illuminate\Mail\Mailable;

class WalletCreditedMail extends Mailable
{
    public $user;
    public $amount;
    public $transaction;

    public $senderName;

    public function __construct($user, $amount, $transaction, $senderName)
    {
        $this->user = $user;
        $this->amount = $amount;
        $this->transaction = $transaction;
        $this->senderName = $senderName;
    }

    public function build()
    {
        return $this->subject('Your Wallet has been Credited')
            ->view('emails.wallet_credited');
    }
}
