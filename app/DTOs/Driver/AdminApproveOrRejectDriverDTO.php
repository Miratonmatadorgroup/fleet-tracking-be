<?php
namespace App\DTOs\Driver;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AdminApproveOrRejectDriverDTO
{
    public string $driverId;
    public string $action;

    public static function fromRequest(Request $request): self
    {
        $validated = $request->validate([
            'driver_id' => 'required|exists:drivers,id',
            'action' => 'required|in:approve,reject',
        ]);

        return new self(
            $validated['driver_id'],
            $validated['action'],
        );
    }

    public function __construct(string $driverId, string $action)
    {
        $this->driverId = $driverId;
        $this->action = $action;
    }
}
