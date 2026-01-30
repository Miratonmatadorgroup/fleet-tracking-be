<?php
namespace App\Notifications\User;

use App\Models\Partner;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PartnerApplicationDecisionNotification extends Notification
{
    use Queueable;

    public Partner $partner;
    public bool $approved;

    public function __construct(Partner $partner, bool $approved)
    {
        $this->partner = $partner;
        $this->approved = $approved;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $statusMessage = $this->approved
            ? 'approved. You are now active as a partner!'
            : 'rejected. Please contact support for more information.';

        return [
            'title'   => 'Partner Application Decision',
            'message' => "Your partner application has been {$statusMessage}",
            'partner_id' => $this->partner->id,
            'application_status' => $this->approved ? 'approved' : 'rejected',
            'decided_by' => 'admin',
        ];
    }
}
