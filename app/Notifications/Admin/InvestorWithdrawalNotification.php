<?php

namespace App\Notifications\Admin;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;


class InvestorWithdrawalNotification extends Notification
{
    use Queueable;

    protected $investor;

    public function __construct($investor)
    {
        $this->investor = $investor;
    }

    public function via($notifiable)
    {
        return ['database']; // or ['mail', 'database'] if you want emails too
    }

    public function toDatabase($notifiable)
    {
        return [
            'title' => 'Investor Withdrawal Request',
            'message' => "{$this->investor->full_name} has requested to withdraw their investment.",
            'investor_details' => [
                'id'                => $this->investor->id,
                'full_name'         => $this->investor->full_name,
                'email'             => $this->investor->email,
                'phone'             => $this->investor->phone,
                'business_name'     => $this->investor->business_name,
                'bank_name'         => $this->investor->bank_name,
                'account_name'      => $this->investor->account_name,
                'account_number'    => $this->investor->account_number,
                'investment_amount' => $this->investor->investment_amount,
                'payment_method'    => $this->investor->payment_method,
                'address'           => $this->investor->address,
                'status'            => $this->investor->status,
            ],
        ];
    }
}

