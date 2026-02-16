<?php
namespace App\DTOs\RolesAndPermissions;

class GetUserRolesAndPermissionsDTO
{
    public string $identifier;

    public function __construct(array $validated)
    {
        $this->identifier = $validated['identifier'];
    }
}
