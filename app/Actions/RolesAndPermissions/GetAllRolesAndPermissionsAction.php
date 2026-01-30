<?php
namespace App\Actions\RolesAndPermissions;

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class GetAllRolesAndPermissionsAction
{
    public function execute(): array
    {
        $roles = Role::with('permissions')->get()->map(function ($role) {
            return [
                'role' => $role->name,
                'permissions' => $role->permissions->pluck('name')->toArray(),
            ];
        });

        $allPermissions = Permission::pluck('name');

        return [
            'roles' => $roles,
            'all_permissions' => $allPermissions,
        ];
    }
}
