<?php
namespace App\Notifications\Admin;

use App\Models\Investor;
use Illuminate\Notifications\Notification;

class NewInvestorApplicationNotification extends Notification
{
    public Investor $application;

    public function __construct(Investor $application)
    {
        $this->application = $application;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'title' => 'New Investor Application',
            'message' => "{$this->application->full_name} has submitted an investment application.",
            'investor_application_id' => $this->application->id,
        ];
    }
}
