<?php
namespace App\Actions\RolesAndPermissions;

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\DTOs\RolesAndPermissions\AddPermissionsToRoleDTO;

class AddPermissionsToRoleAction
{
    public function execute(AddPermissionsToRoleDTO $dto): Role
    {
        $role = Role::where('name', $dto->role)->first();

        if (!$role) {
            throw new ModelNotFoundException('Role not found');
        }

        foreach ($dto->permissions as $permissionName) {
            $permission = Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'api'
            ]);

            if (!$role->hasPermissionTo($permission)) {
                $role->givePermissionTo($permission);
            }
        }

        return $role;
    }
}
