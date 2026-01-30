<?php
namespace App\Notifications\User;

use App\Models\Investor;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class InvestorApplicationDecisionNotification extends Notification
{
    use Queueable;

    public Investor $investor;
    public string $action;

    public function __construct(Investor $investor, string $action)
    {
        $this->investor = $investor;
        $this->action = $action;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $statusMessage = $this->action === 'approve'
            ? 'approved. Welcome aboard!'
            : 'rejected. Please contact support for more information.';

        return [
            'title'   => 'Investor Application Decision',
            'message' => "Your investor application has been {$statusMessage}",
            'investor_id' => $this->investor->id,
            'application_status' => $this->investor->application_status,
            'decided_by' => 'admin',
        ];
    }
}
