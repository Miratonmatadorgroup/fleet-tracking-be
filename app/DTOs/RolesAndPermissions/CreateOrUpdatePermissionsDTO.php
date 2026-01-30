<?php
namespace App\DTOs\RolesAndPermissions;

class CreateOrUpdatePermissionsDTO
{
    public array $permissions;

    public function __construct(array $permissions)
    {
        $this->permissions = array_map(function ($perm) {
            return [
                'name' => $perm['name'],
                'guard_name' => 'api'
            ];
        }, $permissions);
    }
}
