<?php
namespace App\DTOs\RolesAndPermissions;

class AdminEditRoleWithPermissionsDTO
{
    public string $role_id;
    public string $role;
    public array $permissions;

    public function __construct(array $validated)
    {
        $this->role_id = $validated['role_id'];
        $this->role = $validated['role'];
        $this->permissions = $validated['permissions'];
    }
}
