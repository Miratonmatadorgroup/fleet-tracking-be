<?php
namespace App\Notifications\User;

use App\Models\Driver;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class FleetApplicationDecisionNotification extends Notification
{
    use Queueable;

    public Driver $driver;
    public string $action;

    public function __construct(Driver $driver, string $action)
    {
        $this->driver = $driver;
        $this->action = $action;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $statusMessage = $this->action === 'approve'
            ? "approved. Your fleet addition has been successfully accepted."
            : "rejected. Please contact support for more information.";

        return [
            'title'   => 'Fleet Application Decision',
            'message' => "Your fleet addition request for driver {$this->driver->name} has been {$statusMessage}",
            'driver_id' => $this->driver->id,
            'application_status' => $this->driver->application_status,
            'decided_by' => 'admin',
        ];
    }
}
