<?php

namespace App\Notifications;

use App\Models\DriverRating;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DriverRatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public DriverRating $rating) {}

    /**
     * Notification channels
     */
    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Mail notification
     */
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('You received a new rating')
            ->greeting("Hello {$notifiable->name},")
            ->line("You have received a new rating from a customer.")
            ->line("Rating: {$this->rating->rating}/5")
            ->when($this->rating->comment, fn ($mail) =>
                $mail->line("Comment: \"{$this->rating->comment}\"")
            )
            ->line('Thank you for your service!')
            ->action('View Your Ratings', url('/driver/ratings'))
            ->line('Keep up the good work!');
    }

    /**
     * Database notification
     */
    public function toDatabase($notifiable): array
    {
        return [
            'delivery_id' => $this->rating->delivery_id,
            'customer_id' => $this->rating->customer_id,
            'rating'      => $this->rating->rating,
            'comment'     => $this->rating->comment,
            'message'     => "You received a {$this->rating->rating}-star rating from a customer.",
        ];
    }
}
