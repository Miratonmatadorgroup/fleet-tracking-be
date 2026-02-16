<?php
namespace App\DTOs\RolesAndPermissions;

class CreateRoleWithPermissionsDTO
{
    public string $role;
    public array $permissions;

    public function __construct(array $validatedData)
    {
        $this->role = $validatedData['role'];
        $this->permissions = $validatedData['permissions'];
    }
}
