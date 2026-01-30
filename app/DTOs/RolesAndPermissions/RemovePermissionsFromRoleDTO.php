<?php
namespace App\DTOs\RolesAndPermissions;

class RemovePermissionsFromRoleDTO
{
    public string $role;
    public array $permissions;

    public function __construct(array $validated)
    {
        $this->role = $validated['role'];
        $this->permissions = $validated['permissions'];
    }
}
