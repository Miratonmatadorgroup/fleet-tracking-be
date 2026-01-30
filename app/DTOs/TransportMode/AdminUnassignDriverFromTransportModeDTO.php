<?php
namespace App\DTOs\TransportMode;

class AdminUnassignDriverFromTransportModeDTO
{
    public string $identifier;
    public string $transport_mode_id;

    public function __construct(array $validated)
    {
        $this->identifier = $validated['identifier'];
        $this->transport_mode_id = $validated['transport_mode_id'];
    }
}
