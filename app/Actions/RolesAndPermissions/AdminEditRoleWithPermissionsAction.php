<?php
namespace App\Actions\RolesAndPermissions;


use Spatie\Permission\Models\Role;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\DTOs\RolesAndPermissions\AdminEditRoleWithPermissionsDTO;

class AdminEditRoleWithPermissionsAction
{
    public function execute(AdminEditRoleWithPermissionsDTO $dto): Role
    {
        $role = Role::findOrFail($dto->role_id);

        //Update role name
        $role->name = $dto->role;
        $role->save();

        //Sync permissions
        $role->syncPermissions($dto->permissions);

        return $role;
    }
}
