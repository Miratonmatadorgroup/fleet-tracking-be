<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WalletCreditedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected float $amount;
    protected string $reference;
    protected ?string $description;

    public function __construct(float $amount, string $reference, ?string $description = null)
    {
        $this->amount = $amount;
        $this->reference = $reference;
        $this->description = $description ?? 'Wallet top-up successful.';
    }

    /**
     * Channels: in-app + email
     */
    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'title' => 'Wallet Credited',
            'message' => "Your wallet was credited with ₦" . number_format($this->amount, 2) . ". Ref: {$this->reference}",
            'reference' => $this->reference,
            'description' => $this->description,
        ];
    }

    /**
     * Database channel
     */
    public function toArray($notifiable): array
    {
        return [
            'title' => 'Wallet Credited',
            'message' => "Your wallet has been credited with ₦" . number_format($this->amount, 2) . ". Ref: {$this->reference}",
            'amount' => $this->amount,
            'reference' => $this->reference,
            'description' => $this->description,
        ];
    }
}
