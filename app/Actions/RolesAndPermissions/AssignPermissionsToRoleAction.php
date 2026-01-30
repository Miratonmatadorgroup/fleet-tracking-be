<?php

namespace App\Actions\RolesAndPermissions;

use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\DTOs\RolesAndPermissions\AssignPermissionsToRoleDTO;

class AssignPermissionsToRoleAction
{
    public function execute(AssignPermissionsToRoleDTO $dto): Role
    {
        $role = Role::where('name', $dto->role)->first();

        if (!$role) {
            throw new ModelNotFoundException("Role not found");
        }

        //Assign permissions to role
        $role->syncPermissions($dto->permissions);

        //Assign the same permissions to all users with this role
        $usersWithRole = User::role($dto->role)->get();
        foreach ($usersWithRole as $user) {
            $user->syncPermissions($dto->permissions);
        }

        return $role;
    }
}
