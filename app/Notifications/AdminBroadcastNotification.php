<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use App\Mail\AdminBroadcastMailable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class AdminBroadcastNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected string $messageText;
    protected string $subjectLine;

    public function __construct(string $message, string $subject)
    {
        $this->messageText = $message;
        $this->subjectLine = $subject;
    }

    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable)
    {
        if (empty($notifiable->email)) {
            return; // Skip if user has no email
        }
        return new AdminBroadcastMailable(
            $this->messageText,
            $notifiable->name ?? 'User',
            $notifiable->email,
            $this->subjectLine
        );
    }

    public function toArray($notifiable)
    {
        return [
            'message' => $this->messageText,
            'subject' => $this->subjectLine,
        ];
    }
}
