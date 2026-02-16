<?php 
namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WalletDebitNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected float $amount;
    protected string $reference;
    protected string $summary;
    protected ?string $description;

    public function __construct(
        float $amount,
        string $reference,
        string $summary,
        ?string $description = null
    ) {
        $this->amount = $amount;
        $this->reference = $reference;
        $this->summary = $summary;
        $this->description = $description ?? 'Wallet debit successful.';
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'title'       => 'Wallet Debited',
            'message'     => $this->summary,
            'amount'      => $this->amount,
            'reference'   => $this->reference,
            'description' => $this->description,
        ];
    }

    public function toArray($notifiable): array
    {
        return [
            'title'       => 'Wallet Debited',
            'message'     => $this->summary,
            'amount'      => $this->amount,
            'reference'   => $this->reference,
            'description' => $this->description,
        ];
    }
}

