<?php
namespace App\DTOs\RolesAndPermissions;

class UnassignUserRoleDTO
{
    public string $identifier;
    public string $role;

    public function __construct(array $validated)
    {
        $this->identifier = $validated['identifier'];
        $this->role = $validated['role'];
    }
}
