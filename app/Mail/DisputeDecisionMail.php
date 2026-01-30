<?php

namespace App\Mail;

use App\Models\Dispute;
use Illuminate\Mail\Mailable;

class DisputeDecisionMail extends Mailable
{
    public Dispute $dispute;
    public string $userName;

    public function __construct(Dispute $dispute, string $userName)
    {
        $this->dispute = $dispute;
        $this->userName = $userName;
    }

    public function build()
    {
        return $this->subject('Your Dispute Has Been Updated')
            ->view('emails.disputes.dispute_decision')
            ->with([
                'name' => $this->userName,
                'dispute' => $this->dispute,
            ]);
    }
}
