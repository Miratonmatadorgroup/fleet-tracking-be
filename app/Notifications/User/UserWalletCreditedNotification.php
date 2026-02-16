<?php
namespace App\Notifications\User;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\DatabaseMessage;


class UserWalletCreditedNotification extends Notification
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
            'title' => 'Wallet Credited',
            'message' => 'Your wallet has been credited with ₦' . number_format($this->amount, 2) . '.',
            'amount' => $this->amount,
            'description' => $this->description,
            'time' => now()->toDateTimeString(),
        ];
    }
}
