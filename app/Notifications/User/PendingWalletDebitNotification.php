<?php
namespace App\Notifications\User;

use Illuminate\Notifications\Notification;

class PendingWalletDebitNotification extends Notification
{
    public float $amount;
    public ?string $description;

    public function __construct(float $amount, ?string $description = null)
    {
        $this->amount = $amount;
        $this->description = $description;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'title' => 'Pending Wallet Debit',
            'message' => 'Your wallet has insufficient funds for a debit of ₦' . number_format($this->amount, 2) . '. It has been scheduled as a pending debit.',
            'amount' => $this->amount,
            'description' => $this->description,
            'time' => now()->toDateTimeString(),
        ];
    }
}
