<?php
namespace App\Actions\RolesAndPermissions;

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\DTOs\RolesAndPermissions\RemovePermissionsFromRoleDTO;

class RemovePermissionsFromRoleAction
{
    public function execute(RemovePermissionsFromRoleDTO $dto): Role
    {
        $role = Role::where('name', $dto->role)->first();

        if (!$role) {
            throw new ModelNotFoundException('Role not found');
        }

        foreach ($dto->permissions as $permissionName) {
            $permission = Permission::where('name', $permissionName)->first();

            if ($permission && $role->hasPermissionTo($permission)) {
                $role->revokePermissionTo($permission);
            }
        }

        return $role;
    }
}
