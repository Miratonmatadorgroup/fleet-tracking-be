<?php
namespace App\DTOs\TransportMode;

class DeleteTransportModeDTO
{
    public function __construct(
        public readonly string $adminId,
        public readonly string $transportModeId
    ) {}
}
